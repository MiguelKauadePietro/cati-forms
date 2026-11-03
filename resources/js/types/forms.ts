import type { QuestionType } from '@/api/forms';

export type QuestionDraft = {
    key: number;
    type: QuestionType;
    prompt: string;
    options: { key: number; label: string }[];
};
