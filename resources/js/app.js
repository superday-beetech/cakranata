import { createApp } from "vue";
// import HelloVue from "./components/HelloVue.vue";
import RegisteredEndpoints from "./components/RouteRegistry/RegisteredEndpoints.vue";
/*
const element = document.getElementById("vue-test");
if (element) {
  createApp(HelloVue).mount(element);
}
 */
const routeRegistryRoot = document.getElementById("routeRegistryVue");
if (routeRegistryRoot) {
  createApp(RegisteredEndpoints, {
    apiBase: routeRegistryRoot.dataset.apiBase,
  }).mount(routeRegistryRoot);
}
