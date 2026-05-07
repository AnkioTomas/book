import './view.js'
import {createTOCView} from './ui/tree.js'
import {createMenu} from './ui/menu.js'
import {Overlayer} from './overlayer.js'

const getCSS = ({spacing, justify, hyphenate}) => `
    @namespace epub "http://www.idpf.org/2007/ops";
    html {
        color-scheme: light dark;
    }
    /* https://github.com/whatwg/html/issues/5426 */
    @media (prefers-color-scheme: dark) {
        a:link {
            color: lightblue;
        }
    }
    p, li, blockquote, dd {
        line-height: ${spacing};
        text-align: ${justify ? 'justify' : 'start'};
        -webkit-hyphens: ${hyphenate ? 'auto' : 'manual'};
        hyphens: ${hyphenate ? 'auto' : 'manual'};
        -webkit-hyphenate-limit-before: 3;
        -webkit-hyphenate-limit-after: 2;
        -webkit-hyphenate-limit-lines: 2;
        hanging-punctuation: allow-end last;
        widows: 2;
    }
    /* prevent the above from overriding the align attribute */
    [align="left"] { text-align: left; }
    [align="right"] { text-align: right; }
    [align="center"] { text-align: center; }
    [align="justify"] { text-align: justify; }

    pre {
        white-space: pre-wrap !important;
    }
    aside[epub|type~="endnote"],
    aside[epub|type~="footnote"],
    aside[epub|type~="note"],
    aside[epub|type~="rearnote"] {
        display: none;
    }
`

const $ = document.querySelector.bind(document)

const locales = 'en'
const percentFormat = new Intl.NumberFormat(locales, {style: 'percent'})
const listFormat = new Intl.ListFormat(locales, {style: 'short', type: 'conjunction'})

const formatLanguageMap = x => {
    if (!x) return ''
    if (typeof x === 'string') return x
    const keys = Object.keys(x)
    return x[keys[0]]
}

const formatOneContributor = contributor => typeof contributor === 'string'
    ? contributor : formatLanguageMap(contributor?.name)

const formatContributor = contributor => Array.isArray(contributor)
    ? listFormat.format(contributor.map(formatOneContributor))
    : formatOneContributor(contributor)

class Reader {
    #tocView
    style = {
        spacing: 1.4,
        justify: true,
        hyphenate: true,
    }
    annotations = new Map()
    annotationsByValue = new Map()
    external = {
        title: '',
        onRelocate: null,
        onLoad:null,
    }

    closeSideBar() {
        $('#dimming-overlay').classList.remove('show')
        $('#side-bar').classList.remove('show')
    }

    constructor() {
        $('#side-bar-button').addEventListener('click', () => {
            $('#dimming-overlay').classList.add('show')
            $('#side-bar').classList.add('show')
        })
        $('#dimming-overlay').addEventListener('click', () => this.closeSideBar())

        const menu = createMenu([
            {
                name: 'layout',
                label: 'Layout',
                type: 'radio',
                items: [
                    ['Paginated', 'paginated'],
                    ['Scrolled', 'scrolled'],
                ],
                onclick: value => {
                    this.view?.renderer.setAttribute('flow', value)
                },
            },
        ])
        menu.element.classList.add('menu')

        $('#menu-button').append(menu.element)
        $('#menu-button > button').addEventListener('click', () =>
            menu.element.classList.toggle('show'))
        menu.groups.layout.select('paginated')
    }

    async open(file, options = {}) {
        this.external.title = options.title || ''
        this.external.onRelocate = typeof options.onRelocate === 'function'
            ? options.onRelocate
            : null
        this.external.onLoad = typeof options.onLoad === 'function'
            ? options.onLoad
            : null
        this.view = document.createElement('foliate-view')
        document.body.append(this.view)
        await this.view.open(file)
        this.view.addEventListener('load', this.#onLoad.bind(this))
        this.view.addEventListener('relocate', this.#onRelocate.bind(this))

        const {book} = this.view
        book.transformTarget?.addEventListener('data', ({detail}) => {
            detail.data = Promise.resolve(detail.data).catch(e => {
                console.error(new Error(`Failed to load ${detail.name}`, {cause: e}))
                return ''
            })
        })
        this.view.renderer.setStyles?.(getCSS(this.style))
        if (this.external.onLoad) {
            await Promise.resolve(this.external.onLoad(this.view))
        } else {
            await this.view.renderer.next()
        }

        $('#header-bar').style.visibility = 'visible'
        $('#nav-bar').style.visibility = 'visible'
        $('#left-button').addEventListener('click', () => this.view.goLeft())
        $('#right-button').addEventListener('click', () => this.view.goRight())

        const slider = $('#progress-slider')
        slider.dir = book.dir
        slider.addEventListener('input', e =>
            this.view.goToFraction(parseFloat(e.target.value)))
        for (const fraction of this.view.getSectionFractions()) {
            const option = document.createElement('option')
            option.value = fraction
            $('#tick-marks').append(option)
        }

        document.addEventListener('keydown', this.#handleKeydown.bind(this))

        const title = this.external.title
            || formatLanguageMap(book.metadata?.title)
            || 'Untitled Book'
        document.title = title
        $('#side-bar-title').innerText = title
        $('#side-bar-author').innerText = formatContributor(book.metadata?.author)
        Promise.resolve(book.getCover?.())?.then(blob =>
            blob ? $('#side-bar-cover').src = URL.createObjectURL(blob) : null)

        const toc = book.toc
        if (toc) {
            this.#tocView = createTOCView(toc, href => {
                this.view.goTo(href).catch(e => console.error(e))
                this.closeSideBar()
            })
            $('#toc-view').append(this.#tocView.element)
        }

        // load and show highlights embedded in the file by Calibre
        const bookmarks = await book.getCalibreBookmarks?.()
        if (bookmarks) {
            const {fromCalibreHighlight} = await import('./epubcfi.js')
            for (const obj of bookmarks) {
                if (obj.type === 'highlight') {
                    const value = fromCalibreHighlight(obj)
                    const color = obj.style.which
                    const note = obj.notes
                    const annotation = {value, color, note}
                    const list = this.annotations.get(obj.spine_index)
                    if (list) list.push(annotation)
                    else this.annotations.set(obj.spine_index, [annotation])
                    this.annotationsByValue.set(value, annotation)
                }
            }
            this.view.addEventListener('create-overlay', e => {
                const {index} = e.detail
                const list = this.annotations.get(index)
                if (list) for (const annotation of list)
                    this.view.addAnnotation(annotation)
            })
            this.view.addEventListener('draw-annotation', e => {
                const {draw, annotation} = e.detail
                const {color} = annotation
                draw(Overlayer.highlight, {color})
            })
            this.view.addEventListener('show-annotation', e => {
                const annotation = this.annotationsByValue.get(e.detail.value)
                if (annotation.note) alert(annotation.note)
            })
        }
    }

    #handleKeydown(event) {
        const k = event.key
        if (k === 'ArrowLeft' || k === 'h') this.view.goLeft()
        else if (k === 'ArrowRight' || k === 'l') this.view.goRight()
    }

    #onLoad({detail: {doc}}) {
        doc.addEventListener('keydown', this.#handleKeydown.bind(this))
    }

    #onRelocate({detail}) {
        const {fraction, location, tocItem, pageItem} = detail
        const percent = percentFormat.format(fraction)
        const loc = pageItem
            ? `Page ${pageItem.label}`
            : `Loc ${location.current}`
        const slider = $('#progress-slider')
        slider.style.visibility = 'visible'
        slider.value = fraction
        slider.title = `${percent} · ${loc}`
        if (tocItem?.href) this.#tocView?.setCurrentHref?.(tocItem.href)

        const payload = {...detail, percent, loc}
        this.external.onRelocate?.(payload)
        document.dispatchEvent(new CustomEvent('reader:relocate', {detail: payload}))
    }
}

const open = async (file, options = {}) => {
    document.body.removeChild($('#drop-target'))
    const reader = new Reader()
    globalThis.reader = reader
    await reader.open(file, options)
}

const dragOverHandler = e => e.preventDefault()
const dropHandler = e => {
    e.preventDefault()
    const item = Array.from(e.dataTransfer.items)
        .find(item => item.kind === 'file')
    if (item) {
        const entry = item.webkitGetAsEntry()
        open(entry.isFile ? item.getAsFile() : entry).catch(e => console.error(e))
    }
}
const dropTarget = $('#drop-target')
dropTarget.addEventListener('drop', dropHandler)
dropTarget.addEventListener('dragover', dragOverHandler)

$('#file-input').addEventListener('change', e =>
    open(e.target.files[0]).catch(e => console.error(e)))
$('#file-button').addEventListener('click', () => $('#file-input').click())

const params = new URLSearchParams(location.search)
const url = params.get('url')
const title = params.get('filename') || ''
const frac = Number.parseFloat(params.get('frac') ?? '')

if (url) {
    let progressTimer = null
    let progressSnap = null
    const saveProgress = () => {
        if (!title || !progressSnap) return
        const {frac, spine, page, percent} = progressSnap
        return fetch('/admin/api/book/progressUpdate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: new URLSearchParams({
                filename: title,
                frac: String(Math.round(frac * 100) / 100),
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
            // 在这里执行：暂停动画、停止实时请求等
            clearTimeout(progressTimer)
            saveProgress()
        }
    });

    open(url, {
        title,
        onRelocate(item) {
            let frac = item.fraction,
                spine = item.section.current,
                page = item.location.current,
                percent = item.percent

            progressSnap = {frac, spine, page, percent}
            scheduleProgressSave()
        },
        async onLoad(view) {
            await view.goToFraction(Math.min(1, Math.max(0, frac)))

            if (!title) return
            const res = await fetch('/admin/api/book/progress?filename=' + encodeURIComponent(title))
            const j = await res.json().catch(() => ({}))
            if (j.code !== 200 || typeof j.data !== 'number' || Number.isNaN(j.data)) return
            let f = j.data
            if (f > 1) f /= 100
            f = Math.min(1, Math.max(0, f))
            if (globalThis.confirm('检测到上次阅读进度，是否跳转？')) {
                await view.goToFraction(f)
            }
        },
    }).catch(e => console.error(e))
} else {
    dropTarget.style.visibility = 'visible'
}
