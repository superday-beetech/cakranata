export interface DashboardDateRange {
    from?: string;
    to?: string;
}

export interface DashboardPeriod {
    from: string;
    to: string;
}

export interface StockSummary {
    total_products: number;
    total_batches: number;
    total_quantity: number;
    stock_value: number;
    expired_batches: number;
}

export interface ProcurementSummary {
    total_receipts: number;
    total_received_quantity: number;
    total_received_value: number;
    total_orders: number;
    total_order_value: number;
}

export interface DistributionSummary {
    total_deliveries: number;
    total_dispatched_quantity: number;
    total_dispatched_value: number;
    total_orders: number;
    total_order_value: number;
}

export interface FinanceSummary {
    cash_in: number;
    cash_out: number;
    net_cash_flow: number;
    total_transactions: number;
    total_expenses: number;
    expense_amount: number;
}

export interface OutstandingItem {
    total_amount: number;
    paid_amount: number;
    outstanding_amount: number;
}

export interface ReturnsSummary {
    purchase: {
        total_returns: number;
        total_value: number;
    };
    sales: {
        total_returns: number;
        total_value: number;
    };
}

export interface ExpiringBatch {
    id: number;
    product_id: number;
    sku: string;
    product_name: string;
    batch_number: string;
    expired_at: string;
    quantity_remaining: number;
    unit_cost: number;
    stock_value: number;
}

export interface DashboardOverview {
    period: DashboardPeriod;
    stock: StockSummary;
    procurement: ProcurementSummary;
    distribution: DistributionSummary;
    finance: FinanceSummary;
    outstanding: {
        supplier: OutstandingItem;
        customer: OutstandingItem;
    };
    returns: ReturnsSummary;
    expiring_batches: ExpiringBatch[];
}

export interface CashFlowItem {
    date: string;
    cash_in: number;
    cash_out: number;
}

export interface TopSellingItem {
    product_id: number;
    sku: string;
    product_name: string;
    total_quantity: number;
    total_value: number;
}

export interface ExpiringBatchParams {
    days?: number;
}

export interface TopSellingParams extends DashboardDateRange {
    limit?: number;
}
