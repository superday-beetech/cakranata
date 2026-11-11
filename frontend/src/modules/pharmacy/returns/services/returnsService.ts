import api from "@/api/client";
import type { CreatePurchaseReturnPayload, CreateSalesReturnPayload } from "../types/returns";

export const returnsService = {
  async listPurchase() {
    return api.get("/pharmacy/returns-api/list-purchase");
  },

  async showPurchase(id: number) {
    return api.get(`/pharmacy/returns-api/show-purchase/${id}`);
  },

  async createPurchase(payload: CreatePurchaseReturnPayload) {
    return api.post("/pharmacy/returns-api/create-purchase", payload);
  },

  async postPurchase(id: number) {
    return api.post(`/pharmacy/returns-api/post-purchase/${id}`);
  },

  async createSales(payload: CreateSalesReturnPayload) {
    return api.post("/pharmacy/returns-api/create-sales", payload);
  },

  async postSales(id: number) {
    return api.post(`/pharmacy/returns-api/post-sales/${id}`);
  },
};

export default returnsService;
