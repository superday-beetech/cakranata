const settings = Object.assign({}, userSettings);

const AdminSettings = {
  // Settings INIT
  AdminSettingsInit() {
    this.ManageThemeLayout();
    this.ManageSidebarType();
    this.ManageBoxedLayout();
    this.ManageDirectionLayout();
    this.ManageDarkThemeLayout();
    this.ManageColorThemeLayout();
    this.ManageCardLayout();
  },

  // Vertical / Horizontal Layout
  ManageThemeLayout() {
    const horizontalLayoutElement = document.getElementById("horizontal-layout");
    const verticalLayoutElement = document.getElementById("vertical-layout");

    switch (settings.Layout) {
      case "horizontal":
        if (horizontalLayoutElement) {
          horizontalLayoutElement.checked = true;
        }
        document.documentElement.setAttribute("data-layout", "horizontal");
        break;
      case "vertical":
        if (verticalLayoutElement) {
          verticalLayoutElement.checked = true;
        }
        document.documentElement.setAttribute("data-layout", "vertical");
        break;
      default:
        break;
    }
  },

  // Full / Mini Sidebar Type
  ManageSidebarType() {
    switch (settings.SidebarType) {
      case "full":
        const fullSidebarElement = document.querySelector("#full-sidebar");
        if (fullSidebarElement) {
          fullSidebarElement.checked = true;
        }
        document.body.setAttribute("data-sidebartype", "full");

        const setSidebarType = () => {
          const width = window.innerWidth > 0 ? window.innerWidth : screen.width;
          if (width < 1300) {
            document.body.setAttribute("data-sidebartype", "mini-sidebar");
          } else {
            document.body.setAttribute("data-sidebartype", "full");
          }
        };
        window.addEventListener("DOMContentLoaded", setSidebarType);
        window.addEventListener("resize", setSidebarType);
        break;

      case "mini-sidebar":
        const miniSidebarElement = document.querySelector("#mini-sidebar");
        if (miniSidebarElement) {
          miniSidebarElement.checked = true;
        }
        document.body.setAttribute("data-sidebartype", "mini-sidebar");
        break;

      default:
        break;
    }
  },

  // Layout Boxed or Full
  ManageBoxedLayout() {
    const boxedLayoutElement = document.getElementById("boxed-layout");
    const fullLayoutElement = document.getElementById("full-layout");

    if (boxedLayoutElement) boxedLayoutElement.checked = true;
    switch (settings.BoxedLayout) {
      case true:
        document.documentElement.setAttribute("data-boxed-layout", "boxed");
        if (boxedLayoutElement) boxedLayoutElement.checked = true;
        break;
      case false:
        document.documentElement.setAttribute("data-boxed-layout", "full");
        if (fullLayoutElement) fullLayoutElement.checked = true;
        break;
      default:
        break;
    }
  },

  // Direction Type
  ManageDirectionLayout() {
    const ltrLayoutElement = document.getElementById("ltr-layout");
    const rtlLayoutElement = document.getElementById("rtl-layout");

    switch (settings.Direction) {
      case "ltr":
        if (ltrLayoutElement) {
          ltrLayoutElement.checked = true;
        }
        document.documentElement.setAttribute("dir", "ltr");
        const offcanvasStart = document.querySelector(".offcanvas-start");
        if (offcanvasStart) {
          offcanvasStart.classList.toggle("offcanvas-end");
          offcanvasStart.classList.remove("offcanvas-start");
        }
        break;
      case "rtl":
        document.documentElement.setAttribute("dir", "rtl");
        const offcanvasEnd = document.querySelector(".offcanvas-end");
        if (offcanvasEnd) {
          offcanvasEnd.classList.toggle("offcanvas-start");
          offcanvasEnd.classList.remove("offcanvas-end");
        }
        if (rtlLayoutElement) {
          rtlLayoutElement.checked = true;
        }
        break;
      default:
        break;
    }
  },

  // Card Type
  ManageCardLayout() {
    const cardWithoutBorderElement = document.getElementById("card-without-border");
    const cardWithBorderElement = document.getElementById("card-with-border");

    if (cardWithoutBorderElement) cardWithoutBorderElement.checked = true;
    switch (settings.cardBorder) {
      case true:
        document.documentElement.setAttribute("data-card", "border");
        if (cardWithBorderElement) cardWithBorderElement.checked = true;
        break;
      case false:
        document.documentElement.setAttribute("data-card", "shadow");
        if (cardWithoutBorderElement) cardWithoutBorderElement.checked = true;
        break;
      default:
        break;
    }
  },

  // Theme Dark or Light
  ManageDarkThemeLayout() {
    const setTheme = (theme, hideElements, showElements, hideElements2) => {
      document.documentElement.setAttribute("data-bs-theme", theme);
      const themeLayoutElement = document.getElementById(`${theme}-layout`);
      if (themeLayoutElement) {
        themeLayoutElement.checked = true;
      }

      hideElements.forEach((el) => document.querySelectorAll(`.${el}`).forEach((e) => (e.style.display = "none")));
      showElements.forEach((el) => document.querySelectorAll(`.${el}`).forEach((e) => (e.style.display = "flex")));
      hideElements2.forEach((el) => document.querySelectorAll(`.${el}`).forEach((e) => (e.style.display = "none")));
    };

    switch (settings.Theme) {
      case "light":
        setTheme("light", ["light-logo"], ["moon"], ["sun"]);
        break;
      case "dark":
        setTheme("dark", ["dark-logo"], ["sun"], ["moon"]);
        break;
      default:
        break;
    }
  },

  // Theme Color
  ManageColorThemeLayout() {
    const { ColorTheme } = settings;
    const colorThemeElement = document.getElementById(ColorTheme);

    if (colorThemeElement) {
      document.documentElement.setAttribute("data-color-theme", ColorTheme);
      colorThemeElement.checked = true;
    }
  },
};

// Initialize settings
AdminSettings.AdminSettingsInit();

// Handle Click

document.addEventListener("click", function (event) {
  const target = event.target;

  // Sidebar toggle
  const sidebarToggler = target.closest(".sidebartoggler");

  if (sidebarToggler) {
    event.preventDefault();

    const wrapper = document.getElementById("main-wrapper");
    if (!wrapper) return;

    if (sidebarToggler.classList.contains("dark-transparent")) {
      wrapper.classList.remove("show-sidebar");
    } else {
      wrapper.classList.toggle("show-sidebar");
    }

    document.querySelectorAll(".sidebarmenu").forEach((el) => {
      el.classList.toggle("close");
    });

    return;
  }

  // Dark theme
  if (target.closest(".dark-layout")) {
    setTheme("dark");
    return;
  }

  // Light theme
  if (target.closest(".light-layout")) {
    setTheme("light");
    return;
  }

  // Layout vertical / horizontal
  if (target.closest("#vertical-layout")) {
    document.documentElement.setAttribute("data-layout", "vertical");
    return;
  }

  if (target.closest("#horizontal-layout")) {
    document.documentElement.setAttribute("data-layout", "horizontal");
    return;
  }

  // Sidebar type
  if (target.closest("#full-sidebar")) {
    document.body.setAttribute("data-sidebartype", "full");
    return;
  }

  if (target.closest("#mini-sidebar")) {
    document.body.setAttribute("data-sidebartype", "mini-sidebar");
    return;
  }

  // Card border / shadow
  if (target.closest("#card-with-border")) {
    document.documentElement.setAttribute("data-card", "border");
    return;
  }

  if (target.closest("#card-without-border")) {
    document.documentElement.setAttribute("data-card", "shadow");
    return;
  }
});

function setTheme(theme) {
  document.documentElement.setAttribute("data-bs-theme", theme);

  const themeLayout = document.getElementById(`${theme}-layout`);
  if (themeLayout) themeLayout.checked = true;

  document.querySelectorAll(".dark-logo").forEach((el) => {
    el.style.display = theme === "dark" ? "none" : "";
  });

  document.querySelectorAll(".light-logo").forEach((el) => {
    el.style.display = theme === "dark" ? "" : "none";
  });

  document.querySelectorAll(".sun").forEach((el) => {
    el.style.display = theme === "dark" ? "flex" : "none";
  });

  document.querySelectorAll(".moon").forEach((el) => {
    el.style.display = theme === "dark" ? "none" : "flex";
  });
}
