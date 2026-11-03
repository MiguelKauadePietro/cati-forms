import { store } from '@/routes/api/v1/forms';

export type CreateFormInput = {
    title: string;
    description: string;
    questions?: { type: 'discursive'; prompt: string }[];
};

export type FormRecord = Omit<CreateFormInput, 'questions'> & {
    id: number;
    creator_id: number | null;
    created_at: string;
    updated_at: string;
    questions: {
        id: number;
        type: 'discursive';
        prompt: string;
        position: number;
    }[];
};

export type FormErrors = Partial<
    Record<
        | keyof CreateFormInput
        | `questions.${number}`
        | `questions.${number}.prompt`
        | `questions.${number}.type`,
        string[]
    >
>;

export class FormApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly errors: FormErrors = {},
    ) {
        super('Não foi possível criar o formulário.');
    }
}

export async function createForm(input: CreateFormInput): Promise<FormRecord> {
    // Read on submission, never during SSR or once at startup: login renews tokens.
    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    const token = xsrfCookie
        ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length))
        : '';

    const response = await fetch(store.url(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(input),
    });

    if (!response.ok) {
        if (response.status === 422) {
            const body = (await response.json()) as { errors: FormErrors };
            throw new FormApiError(response.status, body.errors);
        }
        throw new FormApiError(response.status);
    }

    const body = (await response.json()) as { data: FormRecord };
    return body.data;
}
