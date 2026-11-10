class VMaxlength {
  constructor(selector, options = {}) {
    this.el = typeof selector === "string" ? document.querySelector(selector) : selector;
    this.opts = Object.assign(
      {
        alwaysShow: false,
        threshold: 10,
        warningClass: "vmaxlength-warning",
        limitClass: "vmaxlength-limit",
        placement: "bottom-right-inside",
        appendToParent: false,
        template: "{current}/{max}",
        transition: "top 0.2s, left 0.2s", // animasi smooth
      },
      options
    );

    if (!this.el) return;
    this.max = parseInt(this.el.getAttribute("maxlength"), 10);
    if (isNaN(this.max)) return;

    VMaxlength._injectCSS();
    this._createBadge();
    this._bindEvents();
    this._update();
  }

  static _injectCSS() {
    if (document.getElementById("vmaxlength-style")) return;
    const css = `
      .vmaxlength-badge {
        position: absolute;
        font-size: 10px;
        color: #838181ff;
        z-index: 9999;
        white-space: nowrap;
        pointer-events: none;
        transition: top 0.2s, left 0.2s, opacity 0.2s;
      }
      .vmaxlength-warning {
        background: #ffc107;
        color: #000;
        padding: 2px 6px;
        border-radius: 4px;
      }
      .vmaxlength-limit {
        background: #dc3545;
        color: #fff;
        padding: 2px 6px;
        border-radius: 4px;
      }
    `;
    const style = document.createElement("style");
    style.id = "vmaxlength-style";
    style.textContent = css;
    document.head.appendChild(style);
  }

  _createBadge() {
    this.badge = document.createElement("span");
    this.badge.className = "vmaxlength-badge";
    this.badge.style.opacity = this.opts.alwaysShow ? "1" : "0";
    this.badge.style.transition = this.opts.transition;

    if (this.opts.appendToParent) {
      const parent = this.el.parentNode;
      if (getComputedStyle(parent).position === "static") parent.style.position = "relative";
      parent.appendChild(this.badge);
    } else {
      document.body.appendChild(this.badge);
    }
  }

  _bindEvents() {
    ["input", "focus", "blur"].forEach((ev) => this.el.addEventListener(ev, () => this._update()));
    window.addEventListener("resize", () => this._update());
    window.addEventListener("scroll", () => this._update());
    if (window.visualViewport) {
      window.visualViewport.addEventListener("resize", () => this._update());
    }
  }

  _getLength() {
    return this.el.value.length;
  }

  _renderText(len, left) {
    return this.opts.template.replace("{current}", len).replace("{max}", this.max).replace("{left}", left);
  }

  _update() {
    const len = this._getLength();
    const left = this.max - len;
    this.badge.textContent = this._renderText(len, left);

    this.badge.classList.remove(this.opts.warningClass, this.opts.limitClass);
    if (left <= 0) this.badge.classList.add(this.opts.limitClass);
    else if (left <= this.opts.threshold) this.badge.classList.add(this.opts.warningClass);

    if (this.opts.alwaysShow || len >= this.max - this.opts.threshold) {
      this.badge.style.opacity = "1";
      this._setPosition();
    } else {
      this.badge.style.opacity = "0";
    }
  }

  _setPosition() {
    const rect = this.el.getBoundingClientRect();
    const badgeRect = this.badge.getBoundingClientRect();
    let top = 0,
      left = 0;

    switch (this.opts.placement) {
      case "bottom-right-inside":
        top = rect.bottom - badgeRect.height - 5 + window.scrollY;
        left = rect.right - badgeRect.width - 10 + window.scrollX;
        break;
      case "top-right-inside":
        top = rect.top + 5 + window.scrollY;
        left = rect.right - badgeRect.width - 10 + window.scrollX;
        break;
      case "bottom-left-inside":
        top = rect.bottom - badgeRect.height - 5 + window.scrollY;
        left = rect.left + 10 + window.scrollX;
        break;
      case "top-left-inside":
        top = rect.top + 5 + window.scrollY;
        left = rect.left + 10 + window.scrollX;
        break;
      case "bottom-right-outside":
        top = rect.bottom + 5 + window.scrollY;
        left = rect.right - badgeRect.width + window.scrollX;
        break;
      case "top-right-outside":
        top = rect.top - badgeRect.height - 5 + window.scrollY;
        left = rect.right - badgeRect.width + window.scrollX;
        break;
      case "bottom-left-outside":
        top = rect.bottom + 5 + window.scrollY;
        left = rect.left + window.scrollX;
        break;
      case "top-left-outside":
        top = rect.top - badgeRect.height - 5 + window.scrollY;
        left = rect.left + window.scrollX;
        break;
      default:
        top = rect.bottom + 5 + window.scrollY;
        left = rect.right - badgeRect.width + window.scrollX;
    }

    if (this.opts.appendToParent) {
      const parentRect = this.el.parentNode.getBoundingClientRect();
      this.badge.style.top = `${top - parentRect.top - window.scrollY}px`;
      this.badge.style.left = `${left - parentRect.left - window.scrollX}px`;
      this.badge.style.position = "absolute";
    } else {
      this.badge.style.top = `${top}px`;
      this.badge.style.left = `${left}px`;
      this.badge.style.position = "absolute";
    }
  }
}

// Auto-init via data attribute
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-bs-toggle="maxlength"]').forEach((el) => {
    new VMaxlength(el, {
      alwaysShow: el.dataset.maxlengthAlwaysShow === "true",
      threshold: parseInt(el.dataset.maxlengthThreshold || "10"),
      placement: el.dataset.maxlengthPlacement || "bottom-right-inside",
      appendToParent: el.dataset.maxlengthAppendToParent === "true",
      template: el.dataset.maxlengthTemplate || "{current}/{max}",
    });
  });
});
