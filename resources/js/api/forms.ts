import { mine, store } from '@/routes/api/v1/forms';

export type QuestionType =
    | 'discursive'
    | 'multiple_choice'
    | 'single_choice_with_justification';

export type QuestionInput = {
    type: QuestionType;
    prompt: string;
    options?: { label: string }[];
};

export type CreateFormInput = {
    title: string;
    description: string;
    questions?: QuestionInput[];
};

export type FormRecord = Omit<CreateFormInput, 'questions'> & {
    id: number;
    creator_id: number | null;
    created_at: string;
    updated_at: string;
    questions: {
        id: number;
        type: QuestionType;
        prompt: string;
        position: number;
        options: { id: number; label: string; position: number }[];
    }[];
};

export type FormErrors = Partial<
    Record<keyof CreateFormInput | `questions.${number}${string}`, string[]>
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

export type FormSummary = Omit<FormRecord, 'questions'>;

export type MyFormsPage = {
    data: FormSummary[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export async function listMyForms(page = 1): Promise<MyFormsPage> {
    const response = await fetch(mine.url({ query: { page } }), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(
            response.status === 401
                ? 'Sua sessão expirou. Entre novamente para consultar seus formulários.'
                : 'Não foi possível carregar seus formulários. Tente novamente.',
        );
    }

    return (await response.json()) as MyFormsPage;
}
