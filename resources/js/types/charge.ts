export type Payment = { id: number; amount: string; discount_amount: string; settled_amount: string; paid_at: string; method: string; notes: string | null; charge_id: number | null };
export type Receivable = { id: number; contract_id: number; client: string; client_id: number; contract_status: string; display_status?: string; display_status_label?: string; can_finalize?: boolean; rental_total: string | null; freight_total: string; total_accrued: string | null; total_paid: string; total_discount: string; balance: string | null; next_charge_date: string | null; charge_interval_days: number; days_overdue: number; due_today: boolean; is_collectible: boolean; financial_status: string; notes: string | null; payments?: Payment[]; started_at?: string; ended_at?: string | null };
export type Charge = { id: number; contract_id: number; client: string; rental_amount: string; freight_amount: string; total_amount: string; paid_amount: string; discount_amount: string; settled_amount: string; balance: string; due_date: string; days_overdue: number; due_today: boolean; status: string; notes: string | null; payments: Payment[] };
export const money = (v: string | null | undefined) => v == null ? '—' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v));
export const statuses: Record<string,string> = { PENDING: 'Pendente', PARTIAL: 'Parcialmente pago', PAID: 'Pago', CANCELLED: 'Cancelado' };

export const chargeTiming = (charge: {days_overdue: number; due_today: boolean}): string => {
    if (charge.days_overdue > 0) return `${charge.days_overdue} ${charge.days_overdue === 1 ? 'dia' : 'dias'} em atraso`;
    return charge.due_today ? 'Vence hoje' : '';
};
