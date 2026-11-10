const DEFAULT_TITLE = "ACN ERP";
const APP_NAME = "ACN ERP";

export function installRouteTitleManager(router) {
  router.afterEach((to, from, failure) => {
    // Jangan mengubah title jika navigasi gagal.
    if (failure) return;

    // Ambil title dari matched route terdalam yang memilikinya.
    const routeWithTitle = [...to.matched].reverse().find((record) => record.meta?.title);

    const title = routeWithTitle?.meta?.title;

    document.title = title ? `${title} | ${APP_NAME}` : DEFAULT_TITLE;
  });
}
