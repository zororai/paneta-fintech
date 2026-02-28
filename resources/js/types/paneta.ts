export interface Institution {
    id: number;
    name: string;
    type: 'bank' | 'wallet' | 'fx_provider';
    country: string;
    api_endpoint: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface LinkedAccount {
    id: number;
    user_id: number;
    institution_id: number;
    account_identifier: string;
    currency: string;
    mock_balance: number;
    consent_expires_at: string;
    status: 'active' | 'revoked' | 'expired';
    created_at: string;
    updated_at: string;
    institution?: Institution;
}

export interface TransactionIntent {
    id: number;
    user_id: number;
    issuer_account_id: number;
    acquirer_identifier: string;
    amount: number;
    currency: string;
    status: 'pending' | 'confirmed' | 'executed' | 'failed';
    reference: string;
    created_at: string;
    updated_at: string;
    issuer_account?: LinkedAccount;
    destination_institution?: Institution;
    payment_instruction?: PaymentInstruction;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface PaymentInstruction {
    id: number;
    transaction_intent_id: number;
    instruction_payload: Record<string, unknown>;
    signed_hash: string;
    status: 'generated' | 'sent' | 'confirmed';
    created_at: string;
    updated_at: string;
}

export interface AuditLog {
    id: number;
    user_id: number | null;
    action: string;
    entity_type: string;
    entity_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface DashboardData {
    total_balance: number;
    accounts: LinkedAccount[];
    accounts_by_currency: Record<string, { count: number; total: number }>;
    recent_transactions: TransactionIntent[];
    last_refresh: string;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface AdminStats {
    total_users: number;
    verified_users: number;
    total_transactions: number;
    executed_transactions: number;
    failed_transactions: number;
    pending_transactions: number;
    total_volume: number;
    today_volume: number;
    transaction_fees: number;
    subscription_revenue: number;
    ads_revenue: number;
    total_revenue: number;
    platform_fee_rate: number;
    current_period: string;
    transaction_stats: {
        executed: {
            count: number;
            avg_completion_time: number;
        };
        pending: {
            count: number;
            avg_pending_time: number;
        };
        failed: {
            count: number;
        };
    };
    demographics: {
        by_country: Array<{ country: string; count: number }>;
        by_gender: Array<{ gender: string; count: number }>;
        by_age_group: Array<{ age_group: string; count: number }>;
    };
    flagged_data: {
        transactions: Array<{
            id: number;
            type: string;
            reference: string;
            user_id: number;
            user_name: string;
            reason: string;
            recommended_action: string;
            created_at: string;
        }>;
        users: Array<{
            id: number;
            type: string;
            name: string;
            email: string;
            risk_tier: string;
            is_suspended: boolean;
            reason: string;
            recommended_action: string;
        }>;
    };
    linked_accounts_stats: {
        total: number;
        active: number;
        failed: number;
        pending: number;
        success_rate: number;
        failure_reasons: Array<{ reason: string; count: number }>;
    };
    users_by_role: Record<string, number>;
}
