export type ProductType = 'QUANTITY' | 'INDIVIDUAL';

export type Product = {
    id: number;
    name: string;
    type: ProductType;
    type_label: string;
    default_price: string | null;
    unit: string | null;
    stock_total: number | null;
    available: number | null;
    active: boolean;
    active_label: string;
};

export type ProductTypeOption = {
    value: ProductType;
    label: string;
};
