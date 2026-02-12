import { Config } from 'ziggy-js';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_admin: boolean;
    is_subscribed?: boolean;
    quota?: {
        used: number;
        limit: number;
        remaining: number;
        plan: string;
    };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        guest?: {
            quota: {
                used: number;
                limit: number;
                remaining: number;
            };
        };
    };
    ziggy: Config & { location: string };
};
