export function showNotify(message, type = "success", timeout = 5000, title = null, icon = null, position = "top-right") {
  const types = {
    success: {
      class: "success",
      icon: "ti ti-circle-check",
      color: "#198754",
    },
    danger: {
      class: "danger",
      icon: "ti ti-circle-x",
      color: "#dc3545",
    },
    error: {
      class: "danger",
      icon: "ti ti-circle-x",
      color: "#dc3545",
    },
    warning: {
      class: "warning",
      icon: "ti ti-alert-triangle",
      color: "#ffc107",
    },
    info: {
      class: "info",
      icon: "ti ti-info-circle",
      color: "#0dcaf0",
    },
  };

  const config = types[type] || types.info;

  const positions = {
    "top-left": {
      top: "0",
      left: "0",
    },
    "top-right": {
      top: "0",
      right: "0",
    },
    "top-center": {
      top: "0",
      left: "50%",
      transform: "translateX(-50%)",
    },
    "bottom-left": {
      bottom: "0",
      left: "0",
      flexDirection: "column-reverse",
    },
    "bottom-right": {
      bottom: "0",
      right: "0",
      flexDirection: "column-reverse",
    },
    "bottom-center": {
      bottom: "0",
      left: "50%",
      transform: "translateX(-50%)",
      flexDirection: "column-reverse",
    },
  };

  const safePosition = positions[position] ? position : "top-right";

  // Normalisasi pesan menjadi teks biasa
  function toText(value) {
    if (value === null || value === undefined) {
      return "";
    }

    if (value instanceof Error) {
      return value.message;
    }

    if (Array.isArray(value)) {
      return value.map(toText).join(", ");
    }

    if (typeof value === "object") {
      try {
        return JSON.stringify(value);
      } catch {
        return "[Data tidak dapat ditampilkan]";
      }
    }

    return String(value);
  }

  const safeMessage = toText(message);
  const safeTitle = title ? toText(title) : "";

  // Container
  const containerId = `alert-container-${safePosition}`;

  let container = document.getElementById(containerId);

  if (!container) {
    container = document.createElement("div");
    container.id = containerId;

    Object.assign(container.style, {
      position: "fixed",
      zIndex: "9999",
      display: "flex",
      flexDirection: "column",
      gap: "0.75rem",
      maxWidth: "380px",
      width: "100%",
      padding: "1rem",
      pointerEvents: "none",
    });

    Object.assign(container.style, positions[safePosition]);

    document.body.appendChild(container);
  }

  // Alert
  const alert = document.createElement("div");

  alert.classList.add("alert", `alert-${config.class}`, "alert-dismissible", "fade", "show", "shadow-sm", "border-0", "bg-white", "m-0", "overflow-hidden");

  Object.assign(alert.style, {
    position: "relative",
    borderRadius: "0.5rem",
    borderLeft: `4px solid ${config.color}`,
    transition: "opacity 0.3s ease, transform 0.3s ease",
    pointerEvents: "auto",
  });

  alert.setAttribute("role", "alert");
  alert.setAttribute("aria-live", "polite");

  // Wrapper
  const wrapper = document.createElement("div");
  wrapper.className = "d-flex align-items-start p-2";

  // Icon
  const iconElement = document.createElement("i");

  const safeIcon = icon || config.icon;

  // Validasi nama class ikon
  const iconClasses = String(safeIcon)
    .split(/\s+/)
    .filter((className) => /^[a-zA-Z0-9_-]+$/.test(className));

  iconElement.classList.add(...iconClasses, "fs-4", "me-3", "flex-shrink-0", `text-${config.class}`);

  // Content
  const content = document.createElement("div");
  content.className = "flex-grow-1 pe-2";

  if (safeTitle) {
    const heading = document.createElement("h6");

    heading.className = "alert-heading mb-1 fw-bold text-dark fs-6";

    heading.textContent = safeTitle;

    content.appendChild(heading);
  }

  const body = document.createElement("div");

  body.className = "alert-body text-secondary small lh-sm";
  body.textContent = safeMessage;

  content.appendChild(body);

  // Close button
  const closeButton = document.createElement("button");

  closeButton.type = "button";
  closeButton.className = "btn-close ms-auto";
  closeButton.setAttribute("aria-label", "Close");
  closeButton.style.fontSize = "0.75rem";

  wrapper.append(iconElement, content);

  if (timeout === false) {
    wrapper.appendChild(closeButton);
  }

  alert.appendChild(wrapper);

  // Progress bar
  const timerBar = document.createElement("div");

  timerBar.className = "alert-timer";

  Object.assign(timerBar.style, {
    position: "absolute",
    bottom: "0",
    left: "0",
    height: "3px",
    width: "100%",
    backgroundColor: config.color,
    opacity: "0.75",
  });

  alert.appendChild(timerBar);
  container.appendChild(alert);

  // Lifecycle
  let closed = false;
  let paused = false;
  let animationFrameId = null;

  let remainingTime = timeout === false ? 0 : Math.max(0, Number(timeout) || 0);

  let startTime = 0;

  function closeAlert() {
    if (closed) return;

    closed = true;

    if (animationFrameId !== null) {
      cancelAnimationFrame(animationFrameId);
    }

    alert.classList.remove("show");

    alert.style.opacity = "0";
    alert.style.transform = "scale(0.95)";

    setTimeout(() => {
      alert.remove();

      if (container.children.length === 0) {
        container.remove();
      }
    }, 300);
  }

  closeButton.addEventListener("click", closeAlert);

  // Timer
  function animate() {
    if (closed || paused) return;

    const elapsed = Date.now() - startTime;

    const progress = Math.max(0, 100 - (elapsed / remainingTime) * 100);

    timerBar.style.width = `${progress}%`;

    if (elapsed < remainingTime) {
      animationFrameId = requestAnimationFrame(animate);
    } else {
      closeAlert();
    }
  }

  if (timeout !== false) {
    if (remainingTime <= 0) {
      closeAlert();
    } else {
      startTime = Date.now();

      alert.addEventListener("mouseenter", () => {
        if (closed || paused) return;

        paused = true;

        if (animationFrameId !== null) {
          cancelAnimationFrame(animationFrameId);
        }

        remainingTime = Math.max(0, remainingTime - (Date.now() - startTime));
      });

      alert.addEventListener("mouseleave", () => {
        if (closed || !paused) return;

        paused = false;
        startTime = Date.now();

        animationFrameId = requestAnimationFrame(animate);
      });

      animationFrameId = requestAnimationFrame(animate);
    }
  } else {
    timerBar.style.display = "none";
  }

  return {
    close: closeAlert,
    element: alert,
  };
}
