export type MovementType = 'WITHDRAWAL' | 'RETURN';

export type MovementTypeOption = {
    value: MovementType;
    label: string;
};

export type MovementContractOption = {
    id: number;
    number: number;
    client_name: string;
};

export type MovementContract = {
    id: number;
    number: number;
    client: {
        id: number;
        name: string;
    };
    items: Array<{
        id: number;
        product: {
            id: number;
            name: string;
            type: string;
        };
        current_quantity: number;
    }>;
};

export type Movement = {
    id: number;
    type: MovementType;
    type_label: string;
    occurred_at: string | null;
    notes: string | null;
    contract: {
        id: number;
        number: number;
        client: {
            id: number;
            name: string;
        };
    };
    items: Array<{
        id: number;
        contract_item_id: number;
        quantity: number;
        product: {
            id: number;
            name: string;
        };
    }>;
};
