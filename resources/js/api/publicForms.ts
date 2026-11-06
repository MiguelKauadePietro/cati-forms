import { getXsrfToken } from '@/api/forms';
import type { FormRecord } from '@/api/forms';
import { show } from '@/routes/api/v1/public/forms';
import { store } from '@/routes/api/v1/public/forms/submissions';

export type PublicForm = Pick<
    FormRecord,
    | 'public_id'
    | 'title'
    | 'description'
    | 'request_contact_email'
    | 'questions'
>;
export type PublicQuestion = PublicForm['questions'][number];
export type AnswerInput = {
    question_id: number;
    text?: string;
    option_ids?: number[];
    justification?: string;
};
export type SubmissionInput = {
    contact_email?: string;
    answers: AnswerInput[];
};
export type SubmissionErrors = Record<string, string[]>;

export class PublicFormApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly errors: SubmissionErrors = {},
    ) {
        super('Não foi possível concluir a solicitação.');
    }
}

export async function getPublicForm(publicId: string): Promise<PublicForm> {
    const response = await fetch(show.url(publicId), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new PublicFormApiError(response.status);
    return ((await response.json()) as { data: PublicForm }).data;
}

export async function submitPublicForm(
    publicId: string,
    input: SubmissionInput,
): Promise<void> {
    const response = await fetch(store.url(publicId), {
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
        const errors =
            response.status === 422
                ? ((await response.json()) as { errors: SubmissionErrors })
                      .errors
                : {};
        throw new PublicFormApiError(response.status, errors);
    }
}
