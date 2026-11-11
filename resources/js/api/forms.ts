import { index, mine, show, store } from '@/routes/api/v1/forms';
import { index as submissionsIndex } from '@/routes/api/v1/forms/submissions';
import { csv, pdf } from '@/routes/api/v1/forms/exports';

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
    request_contact_email?: boolean;
};

export type FormRecord = Omit<
    CreateFormInput,
    'questions' | 'request_contact_email'
> & {
    id: number;
    creator_id: number | null;
    public_id: string;
    public_url: string;
    request_contact_email: boolean;
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

export function getXsrfToken(): string {
    // Read on submission, never during SSR or once at startup: login renews tokens.
    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    return xsrfCookie
        ? decodeURIComponent(xsrfCookie.slice('XSRF-TOKEN='.length))
        : '';
}

export async function createForm(input: CreateFormInput): Promise<FormRecord> {
    const response = await fetch(store.url(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
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

export type AnswerRecord = {
    id: number;
    question_id: number;
    text: string | null;
    justification: string | null;
    options: { id: number; label: string; position: number }[];
};

export type SubmissionRecord = {
    id: number;
    form_id: number;
    contact_email: string | null;
    created_at: string;
    answers: AnswerRecord[];
};

export type SubmissionsPage = Omit<MyFormsPage, 'data'> & {
    data: SubmissionRecord[];
};

async function readForms<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        throw new Error(
            response.status === 401
                ? 'Sua sessão expirou. Entre novamente para consultar os formulários.'
                : response.status === 404
                  ? 'O formulário não foi encontrado.'
                  : 'Não foi possível carregar os dados. Tente novamente.',
        );
    }
    return (await response.json()) as T;
}

export function listForms(page = 1): Promise<MyFormsPage> {
    return readForms(index.url({ query: { page } }));
}

export async function getForm(form: number): Promise<FormRecord> {
    const body = await readForms<{ data: FormRecord }>(show.url(form));
    return body.data;
}

export function listSubmissions(
    form: number,
    page = 1,
): Promise<SubmissionsPage> {
    return readForms(submissionsIndex.url(form, { query: { page } }));
}

export type ExportFormat = 'csv' | 'pdf';

export async function downloadResponses(
    form: number,
    format: ExportFormat,
): Promise<void> {
    const response = await fetch((format === 'csv' ? csv : pdf).url(form), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        throw new Error(
            response.status === 401
                ? 'Sua sessão expirou. Entre novamente para exportar as respostas.'
                : response.status === 404
                  ? 'O formulário não foi encontrado.'
                  : 'Não foi possível exportar as respostas. Tente novamente.',
        );
    }
    const expectedType = format === 'csv' ? 'text/csv' : 'application/pdf';
    if (!response.headers.get('Content-Type')?.startsWith(expectedType)) {
        throw new Error('O arquivo recebido é inválido. Tente novamente.');
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `formulario-${form}-respostas.${format}`;
    document.body.appendChild(link);
    try {
        link.click();
    } finally {
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
}
