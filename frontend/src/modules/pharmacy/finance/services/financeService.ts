import api from "@/api/client";

import type { CustomerPaymentPayload, CreateFinanceResponse, ExpensePayload, FinanceDateFilter, OutstandingResponse, SupplierPaymentPayload } from "../types/finance";

const BASE_URL = "/pharmacy/finance-api";

const financeService = {
  // Supplier Payments
  async getSupplierPayments(params: FinanceDateFilter = {}) {
    return api.get(`${BASE_URL}/supplier-payments`, {
      params,
    });
  },

  async createSupplierPayment(payload: SupplierPaymentPayload) {
    return api.post<CreateFinanceResponse>(`${BASE_URL}/create-supplier-payments`, payload);
  },

  // Customer Payments
  async getCustomerPayments(params: FinanceDateFilter = {}) {
    return api.get(`${BASE_URL}/customer-payments`, {
      params,
    });
  },

  async createCustomerPayment(payload: CustomerPaymentPayload) {
    return api.post<CreateFinanceResponse>(`${BASE_URL}/create-customer-payments`, payload);
  },

  // Expenses
  async getExpenses(params: FinanceDateFilter = {}) {
    return api.get(`${BASE_URL}/expenses`, {
      params,
    });
  },

  async createExpense(payload: ExpensePayload) {
    return api.post<CreateFinanceResponse>(`${BASE_URL}/create-expenses`, payload);
  },

  // Cash Transactions
  async getCashTransactions(params: FinanceDateFilter = {}) {
    return api.get(`${BASE_URL}/cash-transactions`, {
      params,
    });
  },

  // Outstanding
  async getOutstanding(type: "supplier" | "customer", referenceId: number) {
    return api.get<OutstandingResponse>(`${BASE_URL}/outstandings/${type}/${referenceId}`);
  },
};

export default financeService;
