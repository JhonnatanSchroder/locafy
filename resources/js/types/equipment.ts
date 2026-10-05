export type EquipmentStatus =
    | 'AVAILABLE'
    | 'RENTED'
    | 'MAINTENANCE'
    | 'INACTIVE';

export type EquipmentProductOption = {
    id: number;
    name: string;
};

export type Equipment = {
    id: number;
    name: string;
    brand: string | null;
    notes: string | null;
    status: EquipmentStatus;
    status_label: string;
    product: {
        id: number;
        name: string;
    };
};

export type EquipmentStatusOption = {
    value: EquipmentStatus;
    label: string;
};
