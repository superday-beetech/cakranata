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
      },
      options
    );

    if (!this.el) return;
    this.max = parseInt(this.el.getAttribute("maxlength"), 10);
    if (isNaN(this.max)) return;

    VMaxlength._injectCSS(); // pastikan CSS sudah ada
    this._createBadge();
    this._bindEvents();
    this._update();
  }

  static _injectCSS() {
    if (document.getElementById("vmaxlength-style")) return;
    const css = `
      .vmaxlength-badge {
        position: absolute;
        padding: 2px 6px;
        font-size: 12px;
        border-radius: 6px;
        background: #f0f0f0;
        color: #333;
        z-index: 9999;
        white-space: nowrap;
        pointer-events: none;
        transition: opacity 0.2s;
      }
      .vmaxlength-warning {
        background: #ffc107;
        color: #000;
      }
      .vmaxlength-limit {
        background: #dc3545;
        color: #fff;
      }`;
    const style = document.createElement("style");
    style.id = "vmaxlength-style";
    style.textContent = css;
    document.head.appendChild(style);
  }

  _createBadge() {
    this.badge = document.createElement("span");
    this.badge.className = "vmaxlength-badge";
    this.badge.style.opacity = this.opts.alwaysShow ? "1" : "0";
    if (this.opts.appendToParent) {
      this.el.parentNode.style.position = "relative";
      this.el.parentNode.appendChild(this.badge);
    } else {
      document.body.appendChild(this.badge);
    }
  }

  _bindEvents() {
    ["input", "focus", "blur"].forEach((ev) => {
      this.el.addEventListener(ev, () => this._update());
    });
  }

  _getLength() {
    return this.el.value.length;
  }

  _update() {
    const len = this._getLength();
    const left = this.max - len;

    this.badge.textContent = `${len}/${this.max}`;

    this.badge.classList.remove(this.opts.warningClass, this.opts.limitClass);
    if (left <= 0) {
      this.badge.classList.add(this.opts.limitClass);
    } else if (left <= this.opts.threshold) {
      this.badge.classList.add(this.opts.warningClass);
    }

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
    let top, left;

    switch (this.opts.placement) {
      case "bottom-right-inside":
        top = rect.bottom - badgeRect.height - 5;
        left = rect.right - badgeRect.width - 10;
        break;
      case "top-right-inside":
        top = rect.top + 5;
        left = rect.right - badgeRect.width - 10;
        break;
      case "bottom-left-inside":
        top = rect.bottom - badgeRect.height - 5;
        left = rect.left + 10;
        break;
      case "top-left-inside":
        top = rect.top + 5;
        left = rect.left + 10;
        break;
      default:
        top = rect.bottom + 5;
        left = rect.right - badgeRect.width;
    }

    if (!this.opts.appendToParent) {
      this.badge.style.top = `${top + window.scrollY}px`;
      this.badge.style.left = `${left + window.scrollX}px`;
      this.badge.style.position = "absolute";
    } else {
      this.badge.style.top = `${top - rect.top}px`;
      this.badge.style.left = `${left - rect.left}px`;
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
    });
  });
});
