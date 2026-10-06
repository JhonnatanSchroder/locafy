import type { ProductType } from './product';

export type ContractStatus = 'ACTIVE' | 'RETURNED' | 'FINALIZED' | 'CANCELLED';

export type BillingPeriod = 'DAY' | 'WEEK' | 'MONTH';

export type ContractStatusOption = {
    value: string;
    label: string;
};

export type BillingPeriodOption = {
    value: BillingPeriod;
    label: string;
};

export type ContractClientOption = {
    id: number;
    name: string;
};

export type ContractProductOption = {
    id: number;
    name: string;
    type: ProductType;
    type_label: string;
    default_price: string | null;
};

export type ContractItem = {
    id: number;
    product: ContractProductOption;
    billing_period: BillingPeriod;
    billing_period_label: string;
    unit_price: string;
    current_quantity?: number;
    billable_quantity_days?: number;
    accrued_subtotal?: string | null;
};

export type Freight = {
    id: number;
    quantity: number;
    unit_amount: string;
    total: string;
    occurred_at: string | null;
    notes: string | null;
};

export type ContractAttachment = {
    id: number;
    original_name: string;
    mime_type: string;
    file_size: number;
    created_at: string | null;
    uploaded_by: string | null;
    view_url: string;
    api_view_url: string;
};

export type InitialFreight = {
    id: number | null;
    quantity: number;
    unit_amount: string;
    notes: string;
};

export type Contract = {
    id: number;
    number: number;
    status: ContractStatus;
    status_label: string;
    display_status: string;
    display_status_label: string;
    can_finalize: boolean;
    worksite_address: string | null;
    started_at: string | null;
    ended_at: string | null;
    charge_saturdays: boolean;
    next_charge_date: string | null;
    charge_interval_days: number;
    notes: string | null;
    calculated_until: string | null;
    rental_total: string | null;
    calculation_complete: boolean;
    client: ContractClientOption;
    freight_count: number;
    freight_total: string;
    total_accrued: string | null;
    total_paid: string;
    total_discount: string;
    balance: string | null;
    financial_balance: string | null;
    attachments_count: number;
    attachments?: ContractAttachment[];
    initial_freight?: InitialFreight;
    freights?: Freight[];
    items: ContractItem[];
    movements?: Array<{
        id: number;
        type: string;
        type_label: string;
        occurred_at: string | null;
        items: Array<{
            id: number;
            quantity: number;
            product: {
                id: number;
                name: string;
            };
        }>;
    }>;
};
