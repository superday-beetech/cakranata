import { ref } from "vue";

export type ThemeMode = "light" | "dark";

const theme = ref<ThemeMode>("light");

export function useTheme() {
    function setTheme(value: ThemeMode) {
        theme.value = value;

        document.documentElement.setAttribute(
            "data-bs-theme",
            value
        );

        const darkLayout = document.querySelectorAll(".dark-layout");
        const lightLayout = document.querySelectorAll(".light-layout");

        darkLayout.forEach((el) => {
            (el as HTMLElement).style.display =
                value === "light" ? "flex" : "none";
        });

        lightLayout.forEach((el) => {
            (el as HTMLElement).style.display =
                value === "dark" ? "flex" : "none";
        });

        document.querySelectorAll(".dark-logo").forEach((el) => {
            (el as HTMLElement).style.display =
                value === "dark" ? "flex" : "none";
        });

        document.querySelectorAll(".light-logo").forEach((el) => {
            (el as HTMLElement).style.display =
                value === "light" ? "flex" : "none";
        });
    }

    return {
        theme,
        setTheme,
    };
}
