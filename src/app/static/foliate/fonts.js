/**
 * 微信读书字体（与月读 ui/fonts/catalog.lua 同源）
 * 列表：https://weread.qq.com/feconfig/font/list?type=web_v2
 * 包体：cdn.weread.qq.com/.../*.zip → 内含 .woff
 */

const LIST_URL = 'https://weread.qq.com/feconfig/font/list?type=web_v2'
const CACHE_NAME = 'weread-fonts-v1'
const PREFIX = 'weread:'

/** @type {Map<string, {family: string, blobUrl: string}>} */
const ready = new Map()
/** @type {Promise<object[]>|null} */
let catalogPromise = null

export const isWereadTypeface = value => typeof value === 'string' && value.startsWith(PREFIX)

export const wereadId = value => (isWereadTypeface(value) ? value.slice(PREFIX.length) : '')

export const wereadFamilyName = id => `Weread_${id}`

export const wereadTypeface = id => PREFIX + id

/**
 * @returns {Promise<Array<{id:string,name:string,url:string,preview:string,zipSize:number,fontType:string}>>}
 */
export function fetchWereadCatalog() {
    if (!catalogPromise) {
        catalogPromise = fetch(LIST_URL, {credentials: 'omit'})
            .then(r => {
                if (!r.ok) throw new Error('字体列表 HTTP ' + r.status)
                return r.json()
            })
            .then(data => {
                const items = Array.isArray(data?.items) ? data.items : []
                return items
                    .filter(it => it && (it.font || it.id) && it.url)
                    .map(it => ({
                        id: String(it.font || it.id),
                        name: String(it.fontName || it.name || it.font || it.id),
                        url: String(it.url),
                        preview: String(it.previewImageUrl || it.preview || ''),
                        zipSize: Number(it.zipSize || it.zip_size || 0),
                        fontType: String(it.fontType || ''),
                    }))
                    .sort((a, b) => a.name.localeCompare(b.name, 'zh-CN'))
            })
            .catch(err => {
                catalogPromise = null
                throw err
            })
    }
    return catalogPromise
}

async function extractWoffBlob(zipBlob) {
    const {configure, ZipReader, BlobReader, BlobWriter} = await import('./vendor/zip.js')
    configure({useWebWorkers: false})
    const reader = new ZipReader(new BlobReader(zipBlob))
    try {
        const entries = await reader.getEntries()
        const entry = entries.find(e => !e.directory && /\.woff2?$/i.test(e.filename)
            && !/__macosx|\._/i.test(e.filename))
        if (!entry) throw new Error('zip 内没有 woff')
        const type = /\.woff2$/i.test(entry.filename) ? 'font/woff2' : 'font/woff'
        return await entry.getData(new BlobWriter(type))
    } finally {
        await reader.close().catch(() => {})
    }
}

async function readCachedWoff(id) {
    try {
        const cache = await caches.open(CACHE_NAME)
        const res = await cache.match(id)
        if (!res) return null
        return await res.blob()
    } catch {
        return null
    }
}

async function writeCachedWoff(id, blob) {
    try {
        const cache = await caches.open(CACHE_NAME)
        await cache.put(id, new Response(blob, {
            headers: {'Content-Type': blob.type || 'font/woff'},
        }))
    } catch {
        /* private mode / quota — 忽略，本次仍可用 blob */
    }
}

function installFace(id, blob) {
    const family = wereadFamilyName(id)
    if (ready.has(id)) return ready.get(id)
    const blobUrl = URL.createObjectURL(blob)
    const format = (blob.type || '').includes('woff2') || blobUrl.includes('woff2')
        ? 'woff2'
        : 'woff'
    const style = document.createElement('style')
    style.dataset.wereadFont = id
    style.textContent =
        `@font-face{font-family:'${family}';src:url('${blobUrl}') format('${format}');` +
        `font-display:swap;font-weight:400;font-style:normal;}`
    document.head.append(style)
    const info = {family, blobUrl}
    ready.set(id, info)
    return info
}

/**
 * 确保某款微信读书字体已下载并挂上 @font-face。
 * @param {{id:string,url:string}|string} itemOrId
 * @param {{url?:string}} [meta] 仅传 id 时需要 url
 */
export async function ensureWereadFont(itemOrId, meta = {}) {
    const id = typeof itemOrId === 'string' ? itemOrId : itemOrId.id
    const url = typeof itemOrId === 'string' ? meta.url : itemOrId.url
    if (ready.has(id)) return ready.get(id)

    let blob = await readCachedWoff(id)
    if (!blob) {
        // CDN 对非 weread.qq.com 有 CORS；走本站代理（公开资源，无需登录）
        const proxy = '/index/book/wereadFontZip?id=' + encodeURIComponent(id)
        const res = await fetch(proxy, {credentials: 'omit', redirect: 'error'})
        if (!res.ok) {
            if (url) console.warn('weread font proxy failed', res.status, 'upstream', url)
            throw new Error('字体下载失败 HTTP ' + res.status)
        }
        const zipBlob = await res.blob()
        const magic = new Uint8Array(await zipBlob.slice(0, 4).arrayBuffer())
        if (magic[0] !== 0x50 || magic[1] !== 0x4b) {
            throw new Error('字体代理返回非 zip（' + (res.headers.get('content-type') || 'unknown') + '）')
        }
        blob = await extractWoffBlob(zipBlob)
        await writeCachedWoff(id, blob)
    }
    return installFace(id, blob)
}

/**
 * 拼进正文 CSS 的 font-family。
 * @param {string} typeface weread:id
 * @param {string} [fallbackStack] 字体未就绪时的系统回落栈
 */
export function cssFamilyForTypeface(typeface, fallbackStack = 'sans-serif') {
    if (!isWereadTypeface(typeface)) return fallbackStack
    const family = wereadFamilyName(wereadId(typeface))
    return `'${family}', ${fallbackStack}`
}
