class BookCard extends HTMLElement {
    static get observedAttributes() {
        return ["cover", "title", "author", "description", "show-author", "show-description", "read"];
    }

    constructor() {
        super();
        this.attachShadow({ mode: "open" });
    }

    connectedCallback() {
        this.render();
    }

    attributeChangedCallback() {
        this.render();
    }

    render() {
        const cover = $.escapeHtml(this.getAttribute("cover") || "/static/framework/imageLoader/default.png");
        const title = $.escapeHtml(this.getAttribute("title") || "未命名书籍");
        const author = $.escapeHtml(this.getAttribute("author") || "未知作者");
        const description = $.escapeHtml(this.getAttribute("description") || "");
        const showAuthorAttr = this.getAttribute("show-author");
        const showAuthor = showAuthorAttr === null || String(showAuthorAttr).toLowerCase() !== "false";
        const showReadRibbon = String(this.getAttribute("read") || "").trim() === "1";

        this.shadowRoot.innerHTML = `
                <style>
                    :host {
                        display: block;
                        border: none;
                    }
                    .book-card {
                        display: flex;
                        flex-direction: column;
                        border: none;
                    }
                    .meta {
                        display: block;
                    }
                    .cover-wrap {
                        position: relative;
                        display: block;
                        border-radius: 8px;
                    }
                    .read-ribbon {
                        position: absolute;
                        top: 6px;
                        right: 6px;
                        z-index: 2;
                        font-size: 11px;
                        font-weight: 600;
                        line-height: 1;
                        padding: 4px 7px;
                        border-radius: 999px;
                        color: rgb(var(--mdui-color-on-primary));
                        background: rgb(var(--mdui-color-primary));
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
                        pointer-events: none;
                        letter-spacing: 0.02em;
                    }
                    image-loader {
                        width: 100%;
                        aspect-ratio: 3 / 4;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.18);
                    }
                    .title, .author {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        line-height: 1.4;
                    }
                    .title {
                        margin-top: 8px;
                        font-size: 14px;
                        font-weight: 600;
                        color: rgba(var(--mdui-color-on-surface), 1);
                    }
                    .author {
                        margin-top: 4px;
                        font-size: 12px;
                        color: rgba(var(--mdui-color-on-surface), 0.7);
                    }
                    .description {
                        display: none;
                    }

                    :host([show-description]) .description {
                        display: block;
                        margin-top: 6px;
                        font-size: 12px;
                        line-height: 1.45;
                        color: rgba(var(--mdui-color-on-surface), 0.66);
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }

                </style>
                <div class="book-card">
                    <div class="cover-wrap">
                        <image-loader src="${cover}" no-refer></image-loader>
                        ${showReadRibbon ? `<span class="read-ribbon" part="read-ribbon">已读</span>` : ""}
                    </div>
                    <div class="meta">
                        <div class="title" title="${title}">${title}</div>
                        ${showAuthor ? `<div class="author" title="${author}">${author}</div>` : ""}
                        ${description ? `<div class="description" title="${description}">${description}</div>` : ""}
                    </div>
                </div>
            `;

        $.emitter.emit("imageLoader",this.shadowRoot);
    }
}

customElements.define('book-card', BookCard);