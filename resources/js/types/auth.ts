export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type WorkspaceRole = 'owner' | 'editor';

/** Espacio de trabajo: el personal de la cuenta o una editorial compartida. */
export type Workspace = {
    id: number;
    name: string;
    personal: boolean;
};

export type Auth = {
    user: User;
    publisher: (Workspace & { role: WorkspaceRole | null }) | null;
    publishers: Workspace[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
