export type ClientType = 'INDIVIDUAL' | 'COMPANY';

export type Client = {
    id: number;
    type: ClientType;
    type_label: string;
    name: string;
    document: string | null;
    phone: string | null;
    residential_address: string | null;
    notes: string | null;
};

export type ClientTypeOption = {
    value: ClientType;
    label: string;
};
