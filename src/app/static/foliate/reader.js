import './view.js'
import {createTOCView} from './ui/tree.js'
import {Overlayer} from './overlayer.js'
import {createAnnotator} from './annotate.js'
import {
    cssFamilyForTypeface,
    ensureWereadFont,
    fetchWereadCatalog,
    isWereadTypeface,
    wereadId,
    wereadTypeface,
} from './fonts.js'

const $ = (sel, root = document) => root.querySelector(sel)
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)]

const SETTINGS_KEY = 'moon-reader-settings'
const EDGE_ZONE = 64
const HIDE_MS = 4000
const HOLD_MS = 1500

/** 微信字体未就绪时的回落栈；默认思源宋体（与微信读书列表 id=syst 一致） */
const FALLBACK_STACK =
    '-apple-system, BlinkMacSystemFont, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei UI", "Microsoft YaHei", sans-serif'
const DEFAULT_TYPEFACE = wereadTypeface('syst')

const defaults = {
    theme: 'auto',
    flow: 'paginated',
    typeface: DEFAULT_TYPEFACE,
    fontSize: 18,
    lineHeight: 1.6,
    justify: true,
    hyphenate: true,
    progressMode: 'percent',
}

const percentFmt = new Intl.NumberFormat('zh-CN', {style: 'percent', maximumFractionDigits: 0})
const listFmt = new Intl.ListFormat('zh-CN', {style: 'short', type: 'conjunction'})

const formatLanguageMap = x => {
    if (!x) return ''
    if (typeof x === 'string') return x
    const keys = Object.keys(x)
    return x[keys[0]] ?? ''
}

/** 文件名 → 可读书名（去路径、去扩展名） */
const displayNameFromPath = path => {
    if (!path || typeof path !== 'string') return ''
    const base = path.replace(/\\/g, '/').split('/').pop() || path
    return base.replace(/\.(epub|pdf|mobi|azw3?|fb2|cbz)$/i, '') || base
}

const pageTitle = name => (name ? `${name} - 阅读` : '阅读')

const formatOneContributor = contributor =>
    typeof contributor === 'string' ? contributor : formatLanguageMap(contributor?.name)

const formatContributor = contributor =>
    Array.isArray(contributor)
        ? listFmt.format(contributor.map(formatOneContributor))
        : formatOneContributor(contributor)

const loadSettings = () => {
    try {
        const raw = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
        const merged = {...defaults, ...raw}
        // 与整站 theme-switcher 共用 localStorage.theme，避免阅读页另搞一套明暗
        const siteTheme = localStorage.getItem('theme')
        if (siteTheme === 'light' || siteTheme === 'dark' || siteTheme === 'auto') {
            merged.theme = siteTheme
        }
        // 旧版本机字体选项已删，一律迁到微信字体
        if (!isWereadTypeface(merged.typeface)) {
            merged.typeface = DEFAULT_TYPEFACE
        }
        return merged
    } catch {
        return {...defaults}
    }
}

const saveSettings = settings => {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
    if (settings.theme === 'light' || settings.theme === 'dark' || settings.theme === 'auto') {
        localStorage.setItem('theme', settings.theme)
    }
}

const flattenToc = (items, out = []) => {
    for (const item of items || []) {
        if (item.href) out.push(item)
        if (item.subitems?.length) flattenToc(item.subitems, out)
    }
    return out
}

const adjacentChapters = (chapters, currentHref) => {
    const i = chapters.findIndex(c => c.href === currentHref)
    return {
        previous: i > 0 ? chapters[i - 1] : null,
        next: i >= 0 && i < chapters.length - 1 ? chapters[i + 1] : null,
    }
}

const tokenRgb = name => {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    return v ? `rgb(${v})` : ''
}

const pageTheme = () => {
    const dark =
        document.documentElement.classList.contains('mdui-theme-dark') ||
        (document.documentElement.classList.contains('mdui-theme-auto') &&
            matchMedia('(prefers-color-scheme: dark)').matches)
    return {
        scheme: dark ? 'dark' : 'light',
        background: tokenRgb('--mdui-color-surface') || (dark ? '#1c1b1f' : '#fef7ff'),
        foreground: tokenRgb('--mdui-color-on-surface') || (dark ? '#e6e1e5' : '#1c1b1f'),
        link: tokenRgb('--mdui-color-primary') || (dark ? '#d0bcff' : '#6750a4'),
    }
}

const buildPageCss = (settings, theme) => {
    const justify = settings.justify ? 'justify' : 'start'
    const recolor = theme.scheme === 'dark'
    const fontFamily = cssFamilyForTypeface(settings.typeface, FALLBACK_STACK)
    const fontRule = fontFamily
        ? `
body { font-family: ${fontFamily} !important; }
body *:not(pre, code, kbd, samp, tt, pre *, code *, math, math *, svg, svg *) {
    font-family: inherit !important;
}
`
        : ''
    const colors = `
html {
    color-scheme: ${theme.scheme};
    --theme-bg-color: ${theme.background};
    --override-color: true;
    color: ${theme.foreground};
    font-size: ${settings.fontSize}px !important;
}
a:any-link { color: ${theme.link}; }
${fontRule}
${recolor ? `
body, body * {
    color: inherit !important;
    background-color: transparent !important;
    border-color: currentColor !important;
}
a:any-link, a:any-link * { color: ${theme.link} !important; }
` : ''}
`
    const prose = `
@namespace epub "http://www.idpf.org/2007/ops";
p, li, blockquote, dd, dt {
    line-height: ${settings.lineHeight} !important;
    text-align: ${justify} !important;
    -webkit-hyphens: ${settings.hyphenate ? 'auto' : 'manual'};
    hyphens: ${settings.hyphenate ? 'auto' : 'manual'};
    hanging-punctuation: allow-end last;
    widows: 2;
    orphans: 2;
}
[align="left"] { text-align: left !important; }
[align="right"] { text-align: right !important; }
[align="center"] { text-align: center !important; }
[align="justify"] { text-align: justify !important; }
pre { white-space: pre-wrap !important; }
aside[epub|type~="endnote"],
aside[epub|type~="footnote"],
aside[epub|type~="note"],
aside[epub|type~="rearnote"] { display: none; }
`
    return [prose, colors]
}

const searchable = query =>
    query.length >= 2 ||
    /[\u3400-\u9fff\u3040-\u30ff\uac00-\ud7af]/.test(query)

class Reader {
    #tocView
    #chapters = []
    #hideTimer = 0
    #pointerOnChrome = false
    #focusInChrome = false
    #searchGen = null
    #position = null
    #annotator = null
    settings = loadSettings()
    annotations = new Map()
    annotationsByValue = new Map()
    external = {title: '', onRelocate: null, onLoad: null}

    constructor() {
        this.#bindChrome()
        this.#bindSettingsUi()
        this.applyThemeClass()
        this.#loadWereadFontsUi()
    }

    #bindChrome() {
        const show = () => this.showChrome()
        const hold = el => {
            el.addEventListener('pointerenter', () => {
                this.#pointerOnChrome = true
                show()
            })
            el.addEventListener('pointerleave', () => {
                this.#pointerOnChrome = false
                this.hideChromeSoon(HOLD_MS)
            })
            el.addEventListener('focusin', e => {
                if (e.target instanceof Element && e.target.matches(':focus-visible')) {
                    this.#focusInChrome = true
                    show()
                }
            })
            el.addEventListener('focusout', () => {
                this.#focusInChrome = false
            })
        }
        hold($('#chrome-top'))
        hold($('#chrome-bottom'))

        $('#btn-back').addEventListener('click', () => {
            if (history.length > 1) history.back()
            else location.href = '/index/main/book'
        })
        $('#btn-toc').addEventListener('click', () => this.toggleToc())
        $('#toc-close').addEventListener('click', () => this.closeToc())
        $('#toc-scrim').addEventListener('click', () => this.closeToc())

        $('#btn-settings').addEventListener('click', () => this.openPanel('settings'))
        $('#settings-close').addEventListener('click', () => this.closePanel('settings'))

        $('#btn-search').addEventListener('click', () => this.openPanel('search'))
        $('#search-close').addEventListener('click', () => this.closePanel('search'))
        $('#search-scrim').addEventListener('click', () => this.closePanel('search'))

        $('#btn-notes').addEventListener('click', () => this.openPanel('notes'))
        $('#notes-close').addEventListener('click', () => this.closePanel('notes'))

        $('#btn-fullscreen').addEventListener('click', () => this.toggleFullscreen())
        if (!document.fullscreenEnabled) $('#btn-fullscreen').hidden = true

        $('#btn-first').addEventListener('click', () => this.view?.goToFraction(0))
        $('#btn-last').addEventListener('click', () => this.view?.goToFraction(1))
        $('#btn-prev-chapter').addEventListener('click', () => {
            const {previous} = adjacentChapters(this.#chapters, this.#position?.tocItem?.href)
            if (previous?.href) this.view.goTo(previous.href).catch(console.error)
        })
        $('#btn-next-chapter').addEventListener('click', () => {
            const {next} = adjacentChapters(this.#chapters, this.#position?.tocItem?.href)
            if (next?.href) this.view.goTo(next.href).catch(console.error)
        })

        const slider = $('#progress-slider')
        slider.labelFormatter = v => percentFmt.format(Number(v))
        slider.addEventListener('input', e => {
            const v = Number(e.target.value)
            this.#setFolio(percentFmt.format(v))
        })
        slider.addEventListener('change', e => {
            this.view?.goToFraction(Number(e.target.value))
        })

        const searchInput = $('#search-input')
        let searchTimer = 0
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer)
            searchTimer = setTimeout(() => this.runSearch(searchInput.value.trim()), 400)
        })

        document.addEventListener('mousemove', e => {
            if (!this.view) return
            if (e.clientY < EDGE_ZONE || e.clientY > window.innerHeight - EDGE_ZONE) show()
        })

        document.addEventListener('fullscreenchange', () => {
            $('#btn-fullscreen').icon = document.fullscreenElement ? 'fullscreen_exit' : 'fullscreen'
        })

        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (this.settings.theme === 'auto') this.applyAppearance()
        })
    }

    #bindSettingsUi() {
        const syncSeg = (name, value) => {
            for (const btn of $$(`[data-setting="${name}"] button`)) {
                btn.setAttribute('aria-checked', String(btn.dataset.value === String(value)))
            }
        }
        const applyAll = () => {
            syncSeg('theme', this.settings.theme)
            syncSeg('flow', this.settings.flow)
            syncSeg('justify', this.settings.justify)
            syncSeg('progressMode', this.settings.progressMode)
            syncSeg('typeface', this.settings.typeface)
            $('#set-font-size').value = this.settings.fontSize
            $('#set-font-size-val').textContent = String(this.settings.fontSize)
            $('#set-line-height').value = this.settings.lineHeight
            $('#set-line-height-val').textContent = Number(this.settings.lineHeight).toFixed(1)
            $('#set-hyphenate').checked = !!this.settings.hyphenate
        }
        this.#syncSettingsUi = applyAll
        applyAll()

        for (const group of $$('[data-setting]')) {
            group.addEventListener('click', e => {
                const btn = e.target.closest('button[data-value]')
                if (!btn || btn.classList.contains('is-loading')) return
                const key = group.dataset.setting
                let val = btn.dataset.value
                if (key === 'justify') val = val === 'true'
                if (key === 'typeface') {
                    this.#pickWereadFont(btn, val)
                    return
                }
                this.patchSettings({[key]: val})
                applyAll()
            })
        }
        $('#set-font-size').addEventListener('input', e => {
            const n = Number(e.target.value)
            $('#set-font-size-val').textContent = String(n)
            this.patchSettings({fontSize: n})
        })
        $('#set-line-height').addEventListener('input', e => {
            const n = Math.round(Number(e.target.value) * 10) / 10
            $('#set-line-height-val').textContent = n.toFixed(1)
            this.patchSettings({lineHeight: n})
        })
        $('#set-hyphenate').addEventListener('change', e => {
            this.patchSettings({hyphenate: !!e.target.checked})
        })
    }

    #syncSettingsUi = () => {}

    /** @type {Map<string, {id:string,url:string,name:string}>} */
    #wereadMeta = new Map()

    patchSettings(partial) {
        this.settings = {...this.settings, ...partial}
        saveSettings(this.settings)
        if (partial.theme != null) this.applyThemeClass()
        this.applyAppearance()
        if (partial.progressMode != null) this.#refreshFolio()
    }

    applyThemeClass() {
        const root = document.documentElement
        root.classList.remove('mdui-theme-auto', 'mdui-theme-light', 'mdui-theme-dark')
        const t = this.settings.theme
        root.classList.add(t === 'light' ? 'mdui-theme-light' : t === 'dark' ? 'mdui-theme-dark' : 'mdui-theme-auto')
        // 跟主站同一套色源（theme-switcher 写入的 color）
        const color = localStorage.getItem('color') || '#6750A4'
        try {
            globalThis.mdui?.setTheme?.(t === 'light' || t === 'dark' ? t : 'auto')
            globalThis.mdui?.setColorScheme?.(color)
        } catch { /* mdui 未就绪时只靠 class */ }
    }

    applyAppearance() {
        if (!this.view) return
        const theme = pageTheme()
        this.view.renderer.setStyles?.(buildPageCss(this.settings, theme))
        this.view.renderer.setAttribute('flow', this.settings.flow)
        document.body.style.background = theme.background
    }

    async #loadWereadFontsUi() {
        const status = $('#weread-font-status')
        const list = $('#weread-font-list')
        if (!list) return
        try {
            const items = await fetchWereadCatalog()
            this.#wereadMeta.clear()
            list.replaceChildren()
            for (const item of items) {
                this.#wereadMeta.set(item.id, item)
                const btn = document.createElement('button')
                btn.type = 'button'
                btn.dataset.value = wereadTypeface(item.id)
                btn.setAttribute('aria-checked', String(this.settings.typeface === wereadTypeface(item.id)))
                btn.title = item.name
                const mb = item.zipSize ? (item.zipSize / 1048576).toFixed(1) + ' MB' : ''
                btn.innerHTML =
                    (item.preview
                        ? `<img class="font-preview" src="${item.preview}" alt="" loading="lazy" referrerpolicy="no-referrer">`
                        : `<span class="font-sample">字</span>`) +
                    `<span>${escapeHtml(item.name)}</span>` +
                    (mb ? `<span class="font-meta">${mb}</span>` : '')
                list.append(btn)
            }
            if (status) status.textContent = `共 ${items.length} 款，点选后下载到本机缓存`
            let id = wereadId(this.settings.typeface)
            if (!this.#wereadMeta.has(id) && items[0]) {
                id = items[0].id
                this.patchSettings({typeface: wereadTypeface(id)})
                this.#syncSettingsUi()
            }
            const meta = this.#wereadMeta.get(id)
            if (meta) {
                ensureWereadFont(meta).then(() => this.applyAppearance()).catch(console.error)
            }
        } catch (e) {
            console.error(e)
            if (status) status.textContent = '字体列表加载失败（需能访问微信读书 CDN）'
        }
    }

    async #pickWereadFont(btn, value) {
        const id = wereadId(value)
        const meta = this.#wereadMeta.get(id)
        if (!meta) return
        const status = $('#weread-font-status')
        btn.classList.add('is-loading')
        if (status) status.textContent = `正在下载「${meta.name}」…`
        try {
            await ensureWereadFont(meta)
            this.patchSettings({typeface: value})
            this.#syncSettingsUi()
            if (status) status.textContent = `已应用「${meta.name}」（已缓存）`
        } catch (e) {
            console.error(e)
            if (status) status.textContent = `下载失败：${e?.message || e}`
        } finally {
            btn.classList.remove('is-loading')
        }
    }

    showChrome() {
        document.body.classList.add('chrome-shown')
        this.hideChromeSoon(HIDE_MS)
    }

    hideChromeSoon(ms = HIDE_MS) {
        clearTimeout(this.#hideTimer)
        this.#hideTimer = setTimeout(() => {
            if (this.#pointerOnChrome || this.#focusInChrome) return
            if ($('#toc-panel').classList.contains('show') && !this.#isWide()) return
            if ($('#settings-panel').classList.contains('show')) return
            if ($('#search-panel').classList.contains('show')) return
            if ($('#notes-panel').classList.contains('show')) return
            document.body.classList.remove('chrome-shown')
        }, ms)
    }

    toggleChrome() {
        if (document.body.classList.contains('chrome-shown')) {
            document.body.classList.remove('chrome-shown')
            clearTimeout(this.#hideTimer)
        } else this.showChrome()
    }

    #isWide() {
        return matchMedia('(min-width: 721px)').matches
    }

    toggleToc() {
        const panel = $('#toc-panel')
        if (panel.classList.contains('show')) this.closeToc()
        else this.openToc()
    }

    openToc() {
        this.closePanel('settings')
        this.closePanel('search')
        this.closePanel('notes')
        const panel = $('#toc-panel')
        panel.hidden = false
        panel.classList.add('show')
        if (this.#isWide()) {
            panel.classList.add('docked')
            document.body.classList.add('toc-docked')
            $('#toc-scrim').classList.remove('show')
        } else {
            panel.classList.remove('docked')
            document.body.classList.remove('toc-docked')
            $('#toc-scrim').classList.add('show')
        }
        document.body.classList.remove('chrome-shown')
    }

    closeToc() {
        const panel = $('#toc-panel')
        panel.classList.remove('show', 'docked')
        panel.hidden = true
        $('#toc-scrim').classList.remove('show')
        document.body.classList.remove('toc-docked')
    }

    openPanel(name) {
        for (const other of ['settings', 'search', 'notes']) {
            if (other !== name) this.closePanel(other)
        }
        if (!['settings', 'search', 'notes'].includes(name)) return
        if (!this.#isWide()) this.closeToc()
        const panel = $(`#${name}-panel`)
        panel.hidden = false
        panel.classList.add('show')
        // 设置 / 笔记不遮罩；搜索遮罩
        if (name === 'search') $('#search-scrim')?.classList.add('show')
        document.body.classList.remove('chrome-shown')
        if (name === 'search') {
            requestAnimationFrame(() => $('#search-input').shadowRoot?.querySelector('input')?.focus()
                || $('#search-input').focus?.())
        }
        if (name === 'notes') this.#annotator?.renderNotesList?.()
    }

    closePanel(name) {
        const panel = $(`#${name}-panel`)
        if (!panel) return
        panel.classList.remove('show')
        panel.hidden = true
        $(`#${name}-scrim`)?.classList.remove('show')
        if (name === 'search') {
            this.#searchGen = null
            this.view?.clearSearch?.()
        }
    }

    busy() {
        return (
            $('#settings-panel').classList.contains('show') ||
            $('#search-panel').classList.contains('show') ||
            $('#notes-panel').classList.contains('show') ||
            ($('#toc-panel').classList.contains('show') && !this.#isWide()) ||
            !$('#sel-bar')?.hidden
        )
    }

    toggleFullscreen() {
        const p = document.fullscreenElement
            ? document.exitFullscreen()
            : document.documentElement.requestFullscreen()
        p.catch(() => {})
    }

    setStatus(kind, msg = '') {
        const box = $('#status')
        const loading = $('#status-loading')
        const error = $('#status-error')
        if (!kind) {
            box.hidden = true
            return
        }
        box.hidden = false
        loading.hidden = kind !== 'loading'
        error.hidden = kind !== 'error'
        if (kind === 'error') $('#status-error-msg').textContent = msg || '文件可能已损坏，或格式不受支持。'
    }

    async open(file, options = {}) {
        this.external.title = options.title || ''
        this.external.onRelocate = typeof options.onRelocate === 'function' ? options.onRelocate : null
        this.external.onLoad = typeof options.onLoad === 'function' ? options.onLoad : null

        this.setStatus('loading')
        $('#drop-target').style.visibility = 'hidden'

        this.view = document.createElement('foliate-view')
        $('#reader-root').replaceChildren(this.view)

        try {
            await this.view.open(file)
        } catch (e) {
            console.error(e)
            this.setStatus('error', e?.message || '打开失败')
            throw e
        }

        this.view.addEventListener('load', this.#onLoad.bind(this))
        this.view.addEventListener('relocate', this.#onRelocate.bind(this))

        const {book} = this.view
        book.transformTarget?.addEventListener('data', ({detail}) => {
            detail.data = Promise.resolve(detail.data).catch(err => {
                console.error(new Error(`Failed to load ${detail.name}`, {cause: err}))
                return ''
            })
        })

        this.applyAppearance()

        this.#annotator = createAnnotator({
            view: this.view,
            filename: this.external.title || '',
            getChapter: () => this.#position?.tocItem?.label || '',
            getPageNo: () => this.#position?.location?.current || 0,
            onChange: () => this.#annotator?.renderNotesList?.(),
        })

        if (this.external.onLoad) await Promise.resolve(this.external.onLoad(this.view))
        else await this.view.renderer.next()

        await this.#annotator.load()
        this.setStatus(null)

        const title =
            formatLanguageMap(book.metadata?.title) ||
            displayNameFromPath(this.external.title) ||
            '未命名书籍'
        document.title = pageTitle(title)
        $('#chrome-title').textContent = title
        $('#side-bar-title').textContent = title
        $('#side-bar-author').textContent = formatContributor(book.metadata?.author)
        Promise.resolve(book.getCover?.())?.then(blob => {
            if (blob) $('#side-bar-cover').src = URL.createObjectURL(blob)
        })

        const slider = $('#progress-slider')
        slider.disabled = false
        slider.dir = book.dir || 'ltr'

        this.#chapters = flattenToc(book.toc || [])
        if (book.toc?.length) {
            this.#tocView = createTOCView(book.toc, href => {
                this.view.goTo(href).catch(console.error)
                if (!this.#isWide()) this.closeToc()
            })
            $('#toc-view').replaceChildren(this.#tocView.element)
            $('#btn-toc').hidden = false
        } else {
            $('#btn-toc').hidden = true
        }

        document.addEventListener('keydown', this.#handleKeydown.bind(this))
        this.showChrome()
        await this.#loadCalibreHighlights(book)
    }

    async #loadCalibreHighlights(book) {
        const bookmarks = await book.getCalibreBookmarks?.()
        if (!bookmarks) return
        const {fromCalibreHighlight} = await import('./epubcfi.js')
        for (const obj of bookmarks) {
            if (obj.type !== 'highlight') continue
            const value = fromCalibreHighlight(obj)
            const annotation = {value, color: obj.style.which, note: obj.notes}
            const list = this.annotations.get(obj.spine_index)
            if (list) list.push(annotation)
            else this.annotations.set(obj.spine_index, [annotation])
            this.annotationsByValue.set(value, annotation)
        }
        this.view.addEventListener('create-overlay', e => {
            const list = this.annotations.get(e.detail.index)
            if (list) for (const a of list) this.view.addAnnotation(a)
        })
        this.view.addEventListener('draw-annotation', e => {
            const {draw, annotation} = e.detail
            draw(Overlayer.highlight, {color: annotation.color})
        })
        this.view.addEventListener('show-annotation', e => {
            const annotation = this.annotationsByValue.get(e.detail.value)
            if (annotation?.note) alert(annotation.note)
        })
    }

    #handleKeydown(event) {
        if (event.defaultPrevented) return
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'f') {
            event.preventDefault()
            this.openPanel('search')
            return
        }
        if (event.ctrlKey || event.metaKey || event.altKey) return
        if (this.busy()) {
            if (event.key === 'Escape') {
                this.closePanel('settings')
                this.closePanel('search')
                this.closePanel('notes')
                this.#annotator?.hideBar()
                this.closeToc()
            }
            return
        }
        const t = event.target
        if (t instanceof Element && t.closest('input, textarea, select, [contenteditable="true"]')) return

        const k = event.key
        if (k === 'ArrowLeft' || k === 'h' || k === 'PageUp') {
            event.preventDefault()
            this.view.goLeft()
        } else if (k === 'ArrowRight' || k === 'l' || k === 'PageDown' || k === ' ') {
            event.preventDefault()
            this.view.goRight()
        } else if (k === 'Enter') {
            this.toggleChrome()
        } else if (k === 'Escape') {
            document.body.classList.remove('chrome-shown')
            this.closeToc()
            this.closePanel('settings')
            this.closePanel('search')
            this.closePanel('notes')
            this.#annotator?.hideBar()
        }
    }

    #onLoad({detail: {doc}}) {
        doc.addEventListener('keydown', this.#handleKeydown.bind(this))
        doc.addEventListener('click', e => this.#onPageClick(e, doc))
        this.#annotator?.bindSelection(doc)
    }

    #onPageClick(event, doc) {
        if (event.defaultPrevented || event.button !== 0 || this.busy()) return
        if (event.target instanceof Element && event.target.closest('a[href], button, audio, video, [role="button"]')) {
            return
        }
        const sel = (event.view ?? doc.defaultView)?.getSelection?.()
        if (sel && !sel.isCollapsed) return
        this.#annotator?.hideBar()

        const frame = doc.defaultView?.frameElement
        let x = event.clientX
        if (frame instanceof HTMLElement) {
            const rect = frame.getBoundingClientRect()
            const scale = frame.offsetWidth ? rect.width / frame.offsetWidth : 1
            x = rect.left + event.clientX * scale
        }
        const ratio = x / window.innerWidth
        if (ratio < 1 / 3) this.view.goLeft()
        else if (ratio > 2 / 3) this.view.goRight()
        else this.toggleChrome()
    }

    flushAnnotations() {
        return this.#annotator?.flush?.()
    }

    #onRelocate({detail}) {
        this.#position = detail
        const {fraction, location, tocItem, pageItem} = detail
        const slider = $('#progress-slider')
        slider.value = fraction
        slider.title = percentFmt.format(fraction)
        if (tocItem?.href) this.#tocView?.setCurrentHref?.(tocItem.href)

        this.#refreshFolio()
        this.#updateChapterButtons()

        const loc = pageItem ? `第 ${pageItem.label} 页` : `位置 ${location?.current ?? ''}`
        const payload = {...detail, percent: percentFmt.format(fraction), loc}
        this.external.onRelocate?.(payload)
        document.dispatchEvent(new CustomEvent('reader:relocate', {detail: payload}))
    }

    #refreshFolio() {
        if (!this.#position) return
        const mode = this.settings.progressMode
        if (mode === 'none') {
            this.#setFolio('')
            return
        }
        if (mode === 'page') {
            const pages = this.view?.isFixedLayout ? this.#position.section : this.#position.location
            if (pages?.total > 0) {
                this.#setFolio(`${Math.min(pages.total, pages.current + 1)} / ${pages.total}`)
                return
            }
        }
        this.#setFolio(percentFmt.format(this.#position.fraction))
    }

    #setFolio(text) {
        $('#folio').textContent = text
    }

    #updateChapterButtons() {
        const {previous, next} = adjacentChapters(this.#chapters, this.#position?.tocItem?.href)
        $('#btn-prev-chapter').disabled = !previous
        $('#btn-next-chapter').disabled = !next
        const frac = this.#position?.fraction ?? 0
        $('#btn-first').disabled = frac <= 0.001
        $('#btn-last').disabled = frac >= 0.999
    }

    async runSearch(query) {
        const box = $('#search-results')
        const status = $('#search-status')
        box.replaceChildren()
        this.view?.clearSearch?.()
        if (!searchable(query)) {
            status.textContent = query ? '请输入至少两个字符（或一个汉字）' : ''
            return
        }
        status.textContent = '搜索中…'
        const gen = this.view.search({query})
        this.#searchGen = gen
        let total = 0
        try {
            for await (const event of gen) {
                if (this.#searchGen !== gen) return
                if (event === 'done') break
                if (event.progress != null) {
                    status.textContent = `搜索中… ${percentFmt.format(event.progress)}`
                }
                if (event.subitems) {
                    if (event.label) {
                        const h = document.createElement('div')
                        h.className = 'search-group-label'
                        h.textContent = event.label
                        box.append(h)
                    }
                    for (const hit of event.subitems) {
                        total++
                        const btn = document.createElement('button')
                        btn.type = 'button'
                        btn.className = 'search-hit'
                        const before = hit.excerpt?.before ?? ''
                        const match = hit.excerpt?.match ?? hit.excerpt?.text ?? ''
                        const after = hit.excerpt?.after ?? ''
                        btn.innerHTML = `${escapeHtml(before)}<mark>${escapeHtml(match)}</mark>${escapeHtml(after)}`
                        btn.addEventListener('click', () => {
                            this.view.goTo(hit.cfi).catch(console.error)
                            if (!this.#isWide()) this.closePanel('search')
                        })
                        box.append(btn)
                    }
                }
            }
            status.textContent = total ? `共 ${total} 处` : '无匹配'
        } catch (e) {
            console.error(e)
            status.textContent = '搜索失败'
        }
    }
}

const escapeHtml = s =>
    String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')

const open = async (file, options = {}) => {
    const reader = new Reader()
    globalThis.reader = reader
    await reader.open(file, options)
}

const dropTarget = $('#drop-target')
dropTarget.addEventListener('dragover', e => e.preventDefault())
dropTarget.addEventListener('drop', e => {
    e.preventDefault()
    const item = [...e.dataTransfer.items].find(i => i.kind === 'file')
    if (!item) return
    const entry = item.webkitGetAsEntry?.()
    open(entry?.isFile ? item.getAsFile() : entry).catch(console.error)
})
$('#file-input').addEventListener('change', e => open(e.target.files[0]).catch(console.error))
$('#file-button').addEventListener('click', () => $('#file-input').click())

const params = new URLSearchParams(location.search)
const url = params.get('url')
const title = params.get('filename') || ''
const frac = Number.parseFloat(params.get('frac') ?? '')

// 打开前先用文件名占位，避免标签页一直停在「阅读」
if (title) {
    const hint = displayNameFromPath(title)
    document.title = pageTitle(hint)
    const chrome = $('#chrome-title')
    if (chrome && !chrome.textContent) chrome.textContent = hint
}

if (url) {
    let progressTimer = null
    let progressSnap = null
    const saveProgress = () => {
        if (!title || !progressSnap) return
        const {frac: f, spine, page, percent} = progressSnap
        return fetch('/index/book/progressUpdate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: new URLSearchParams({
                filename: title,
                frac: String(Math.round(f * 100) / 100),
                spine: String(spine),
                page: String(page),
                percent: String(percent),
            }),
            keepalive: true,
        }).catch(() => {})
    }
    const scheduleProgressSave = () => {
        clearTimeout(progressTimer)
        progressTimer = setTimeout(saveProgress, 20000)
    }

    window.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            clearTimeout(progressTimer)
            saveProgress()
            globalThis.reader?.flushAnnotations?.()
        }
    })
    window.addEventListener('pagehide', () => {
        clearTimeout(progressTimer)
        saveProgress()
        globalThis.reader?.flushAnnotations?.()
    })

    open(url, {
        title,
        onRelocate(item) {
            progressSnap = {
                frac: item.fraction,
                spine: item.section?.current ?? 0,
                page: item.location?.current ?? 0,
                percent: item.percent,
            }
            scheduleProgressSave()
        },
        async onLoad(view) {
            const initial = Number.isFinite(frac) ? Math.min(1, Math.max(0, frac)) : 0
            await view.goToFraction(initial)

            if (!title) return
            const res = await fetch('/index/book/progress?filename=' + encodeURIComponent(title))
            const j = await res.json().catch(() => ({}))
            if (j.code !== 200 || typeof j.data !== 'number' || Number.isNaN(j.data)) return
            let f = j.data
            if (f > 1) f /= 100
            f = Math.min(1, Math.max(0, f))
            if (Math.abs(f - initial) < 0.01) return
            if (globalThis.confirm('检测到上次阅读进度，是否跳转？')) {
                await view.goToFraction(f)
            }
        },
    }).catch(e => {
        console.error(e)
        globalThis.reader?.setStatus?.('error', e?.message)
    })
} else {
    dropTarget.style.visibility = 'visible'
}
