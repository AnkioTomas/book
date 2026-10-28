/**
 * 阅读页高亮 / 笔记。
 * 数据走 POST /index/stats/annotations（按 device_id=web 的整书快照），
 * 定位用 EPUB CFI，写在 page_ref。
 */
import {Overlayer} from './overlayer.js'

export const DEVICE_ID = 'web'

/** 与 annotations.js 列表页同名，保证颜色一致 */
export const HL_COLORS = {
    yellow: '#d6a900',
    red: '#c43c39',
    blue: '#3678c8',
    green: '#39875b',
    orange: '#c46a19',
    purple: '#8056a8',
}

const COLOR_ORDER = ['yellow', 'green', 'blue', 'orange', 'red', 'purple']

const isCfi = value => typeof value === 'string' && /epubcfi\s*\(/i.test(value)

const nowIso = () => {
    const d = new Date()
    const p = n => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

const resolvePaintColor = color => {
    if (!color) return HL_COLORS.yellow
    if (HL_COLORS[color]) return HL_COLORS[color]
    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) || /^rgba?\(/.test(color)) return color
    return HL_COLORS.yellow
}

/**
 * @param {{
 *   view: import('./view.js').View,
 *   filename: string,
 *   getChapter: () => string,
 *   getPageNo: () => number,
 *   onChange: () => void,
 * }} opts
 */
export function createAnnotator(opts) {
    const {view, filename, getChapter, getPageNo, onChange} = opts
    /** @type {Map<string, object>} cfi -> API item */
    const items = new Map()
    let saveTimer = 0
    let pendingSelection = null
    let editingCfi = null

    const bar = document.getElementById('sel-bar')
    const noteDialog = document.getElementById('note-dialog')
    const noteText = document.getElementById('note-text')
    const noteQuote = document.getElementById('note-quote')
    const notesList = document.getElementById('notes-list')

    const paint = annotation => {
        const color = resolvePaintColor(annotation.color)
        const drawer = annotation.drawer || ''
        if (drawer === 'underline') {
            return (draw) => draw(Overlayer.underline, {color})
        }
        return (draw) => draw(Overlayer.highlight, {color})
    }

    const toFoliate = item => ({
        value: item.page,
        color: resolvePaintColor(item.color),
        note: item.note || '',
        drawer: item.drawer || '',
        _item: item,
    })

    const drawItem = async item => {
        if (!isCfi(item.page)) return
        const annotation = toFoliate(item)
        await view.addAnnotation(annotation)
    }

    const scheduleSave = () => {
        clearTimeout(saveTimer)
        saveTimer = setTimeout(flush, 800)
        onChange?.()
    }

    const flush = () => {
        clearTimeout(saveTimer)
        if (!filename) return
        const annotations = [...items.values()].map(item => ({
            text: item.text || '',
            note: item.note || '',
            drawer: item.drawer || '',
            color: item.color || 'yellow',
            chapter: item.chapter || '',
            pageno: item.pageno || 0,
            page: item.page,
            total_pages: item.total_pages || 0,
            pos0: item.pos0 || item.page,
            pos1: item.pos1 || '',
            datetime: item.datetime,
            datetime_updated: item.datetime_updated || item.datetime,
        }))
        return fetch('/index/stats/annotations', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                filename,
                device_id: DEVICE_ID,
                annotations,
            }),
            keepalive: true,
        }).catch(() => {})
    }

    const renderNotesList = () => {
        if (!notesList) return
        notesList.replaceChildren()
        const list = [...items.values()].sort((a, b) =>
            String(b.datetime_updated || b.datetime).localeCompare(String(a.datetime_updated || a.datetime)))
        if (!list.length) {
            const empty = document.createElement('div')
            empty.className = 'search-status'
            empty.textContent = '还没有高亮或笔记。选中正文即可添加。'
            notesList.append(empty)
            return
        }
        for (const item of list) {
            const row = document.createElement('button')
            row.type = 'button'
            row.className = 'note-row'
            row.innerHTML =
                `<span class="note-swatch" style="background:${resolvePaintColor(item.color)}"></span>` +
                `<span class="note-body">` +
                `<span class="note-text">${escapeHtml(item.text || '（无文本）')}</span>` +
                (item.note ? `<span class="note-memo">${escapeHtml(item.note)}</span>` : '') +
                `<span class="note-meta">${escapeHtml([item.chapter, item.datetime_updated || item.datetime].filter(Boolean).join(' · '))}</span>` +
                `</span>`
            row.addEventListener('click', () => {
                view.goTo(item.page).catch(console.error)
            })
            row.addEventListener('contextmenu', e => {
                e.preventDefault()
                openEditor(item.page)
            })
            notesList.append(row)
        }
    }

    const upsert = async ({cfi, text, color, note, drawer}) => {
        const prev = items.get(cfi)
        const item = {
            text: text ?? prev?.text ?? '',
            note: note ?? prev?.note ?? '',
            drawer: drawer ?? prev?.drawer ?? '',
            color: color ?? prev?.color ?? 'yellow',
            chapter: getChapter?.() || prev?.chapter || '',
            pageno: getPageNo?.() || prev?.pageno || 0,
            page: cfi,
            total_pages: 0,
            pos0: cfi,
            pos1: '',
            datetime: prev?.datetime || nowIso(),
            datetime_updated: nowIso(),
        }
        items.set(cfi, item)
        await drawItem(item)
        renderNotesList()
        scheduleSave()
        return item
    }

    const remove = async cfi => {
        const item = items.get(cfi)
        if (!item) return
        items.delete(cfi)
        await view.deleteAnnotation({value: cfi})
        renderNotesList()
        scheduleSave()
    }

    const hideBar = () => {
        if (!bar) return
        bar.hidden = true
        pendingSelection = null
    }

    const showBar = (doc, range) => {
        if (!bar || !range || range.collapsed) {
            hideBar()
            return
        }
        const text = range.toString().trim()
        if (!text) {
            hideBar()
            return
        }
        const index = view.renderer.getContents()
            .find(c => c.doc === doc)?.index
        if (index == null) return
        const cfi = view.getCFI(index, range)
        pendingSelection = {doc, range, text, cfi}

        const rects = range.getClientRects()
        const last = rects[rects.length - 1]
        if (!last) return
        const frame = doc.defaultView?.frameElement
        let x = last.left + last.width / 2
        let y = last.bottom + 8
        if (frame instanceof HTMLElement) {
            const fr = frame.getBoundingClientRect()
            const scale = frame.offsetWidth ? fr.width / frame.offsetWidth : 1
            x = fr.left + x * scale
            y = fr.top + y * scale
        }
        bar.hidden = false
        const bw = bar.offsetWidth || 220
        const bh = bar.offsetHeight || 44
        const left = Math.min(window.innerWidth - bw - 8, Math.max(8, x - bw / 2))
        const top = Math.min(window.innerHeight - bh - 8, Math.max(8, y))
        bar.style.left = `${left}px`
        bar.style.top = `${top}px`
    }

    const openEditor = (cfi, seedText = '') => {
        editingCfi = cfi
        const item = items.get(cfi)
        if (noteQuote) noteQuote.textContent = item?.text || seedText || ''
        if (noteText) noteText.value = item?.note || ''
        if (noteDialog) noteDialog.open = true
        requestAnimationFrame(() => noteText?.focus?.())
    }

    const bindSelection = doc => {
        const onSel = () => {
            const sel = doc.getSelection?.() || doc.defaultView?.getSelection?.()
            if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
                // 延迟隐藏，留给工具栏点击
                setTimeout(() => {
                    if (!pendingSelection) hideBar()
                }, 50)
                return
            }
            showBar(doc, sel.getRangeAt(0).cloneRange())
        }
        doc.addEventListener('mouseup', onSel)
        doc.addEventListener('touchend', onSel)
        doc.addEventListener('keyup', onSel)
    }

    // 工具栏颜色 / 笔记 / 下划线
    if (bar) {
        bar.addEventListener('mousedown', e => e.preventDefault()) // 保住选区
        bar.addEventListener('click', async e => {
            const btn = e.target.closest('[data-hl]')
            if (!btn || !pendingSelection) return
            const {cfi, text} = pendingSelection
            const action = btn.dataset.hl
            if (action === 'note') {
                await upsert({cfi, text, color: 'yellow'})
                hideBar()
                pendingSelection.doc?.defaultView?.getSelection?.()?.removeAllRanges()
                openEditor(cfi, text)
                return
            }
            if (action === 'underline') {
                await upsert({cfi, text, color: 'red', drawer: 'underline'})
            } else {
                await upsert({cfi, text, color: action, drawer: ''})
            }
            hideBar()
            pendingSelection?.doc?.defaultView?.getSelection?.()?.removeAllRanges()
            pendingSelection = null
        })
    }

    document.getElementById('note-save')?.addEventListener('click', async () => {
        if (!editingCfi) return
        const note = noteText?.value?.trim() || ''
        const prev = items.get(editingCfi)
        await upsert({
            cfi: editingCfi,
            text: prev?.text || noteQuote?.textContent || '',
            note,
            color: prev?.color || 'yellow',
            drawer: prev?.drawer || '',
        })
        if (noteDialog) noteDialog.open = false
        editingCfi = null
    })
    document.getElementById('note-delete')?.addEventListener('click', async () => {
        if (!editingCfi) return
        await remove(editingCfi)
        if (noteDialog) noteDialog.open = false
        editingCfi = null
    })
    document.getElementById('note-cancel')?.addEventListener('click', () => {
        if (noteDialog) noteDialog.open = false
        editingCfi = null
    })

    view.addEventListener('draw-annotation', e => {
        const {draw, annotation} = e.detail
        const item = annotation._item || items.get(annotation.value)
        if (!item) return // Calibre 等外源由 reader.js 原逻辑画
        paint(item)(draw)
    })

    view.addEventListener('show-annotation', e => {
        const {value} = e.detail
        if (!items.has(value)) return
        openEditor(value)
    })

    view.addEventListener('create-overlay', e => {
        const {index} = e.detail
        for (const item of items.values()) {
            if (!isCfi(item.page)) continue
            try {
                const resolved = view.resolveNavigation(item.page)
                if (resolved?.index !== index) continue
                view.addAnnotation(toFoliate(item)).catch(() => {})
            } catch {
                /* ignore bad cfi */
            }
        }
    })

    const load = async () => {
        if (!filename) return
        try {
            const res = await fetch('/index/stats/annotations?filename=' + encodeURIComponent(filename))
            const j = await res.json().catch(() => ({}))
            if (j.code !== 200) return
            const rows = j.data?.annotations || []
            for (const row of rows) {
                if (row.device_id !== DEVICE_ID) continue
                if (!isCfi(row.page)) continue
                items.set(row.page, {
                    text: row.text || '',
                    note: row.note || '',
                    drawer: row.drawer || '',
                    color: row.color || 'yellow',
                    chapter: row.chapter || '',
                    pageno: row.pageno || 0,
                    page: row.page,
                    total_pages: row.total_pages || 0,
                    pos0: row.pos0 || row.page,
                    pos1: row.pos1 || '',
                    datetime: row.datetime,
                    datetime_updated: row.datetime_updated || row.datetime,
                })
            }
            for (const item of items.values()) await drawItem(item)
            renderNotesList()
        } catch (e) {
            console.error('load annotations failed', e)
        }
    }

    return {
        bindSelection,
        load,
        flush,
        hideBar,
        renderNotesList,
        colors: COLOR_ORDER,
    }
}

const escapeHtml = s =>
    String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
