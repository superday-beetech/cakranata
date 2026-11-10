
import api from "@/api/client";
const BASE_URL = "/pharmacy/finance-api";
export interface FinanceDateFilter {
    date_from?: string;
    date_to?: string;
}

export interface SupplierPaymentPayload {
    payment_number: string;
    supplier_id: number;
    purchase_order_id?: number | null;
    payment_date: string;
    amount: number;
    payment_method: string;
    reference_number?: string | null;
    notes?: string | null;
}

export interface CustomerPaymentPayload {
    payment_number: string;
    customer_id: number;
    distribution_order_id?: number | null;
    payment_date: string;
    amount: number;
    payment_method: string;
    reference_number?: string | null;
    notes?: string | null;
}

export interface ExpensePayload {
    expense_number: string;
    expense_date: string;
    category: string;
    description: string;
    amount: number;
    payment_method: string;
    reference_number?: string | null;
    notes?: string | null;
}

export interface OutstandingResponse {
    total_amount: number;
    paid_amount: number;
    outstanding_amount: number;
}

export interface CreateFinanceResponse {
    id: number;
}

const financeService = {
    // Supplier Payments
    async getSupplierPayments(params: FinanceDateFilter = {}) {
        return api.get(`${BASE_URL}/supplier-payments`, {
            params,
        });
    },

    async createSupplierPayment(payload: SupplierPaymentPayload) {
        return api.post(
            `${BASE_URL}/create-supplier-payments`,
            payload
        );
    },

    // Customer Payments
    async getCustomerPayments(params: FinanceDateFilter = {}) {
        return api.get(`${BASE_URL}/customer-payments`, {
            params,
        });
    },

    async createCustomerPayment(payload: CustomerPaymentPayload) {
        return api.post(
            `${BASE_URL}/create-customer-payments`,
            payload
        );
    },

    // Expenses
    async getExpenses(params: FinanceDateFilter = {}) {
        return api.get(`${BASE_URL}/expenses`, {
            params,
        });
    },

    async createExpense(payload: ExpensePayload) {
        return api.post(`${BASE_URL}/create-expenses`, payload);
    },

    // Cash Transactions
    async getCashTransactions(params: FinanceDateFilter = {}) {
        return api.get(
            `${BASE_URL}/cash-transactions`,
            { params }
        );
    },

    // Outstanding
    async getOutstanding(
        type: "supplier" | "customer",
        referenceId: number
    ) {
        return api.get<{
            data: OutstandingResponse;
        }>(
            `${BASE_URL}/outstandings/${type}/${referenceId}`
        );
    },
};

export default financeService;
