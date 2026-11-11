<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Respostas — {{ $form->title }}</title>
    <style>
        @page { margin: 44px 42px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.5; color: #222; }
        h1 { font-size: 19pt; margin: 0 0 12px; }
        h2 { font-size: 14pt; margin: 24px 0 8px; }
        h3 { font-size: 11pt; margin: 18px 0 6px; }
        h1, h2, h3 { page-break-after: avoid; word-wrap: break-word; }
        p { margin: 6px 0; }
        .text { white-space: pre-wrap; word-wrap: break-word; }
        .metadata { color: #555; font-size: 9pt; }
        .submission + .submission { page-break-before: always; }
        ul { margin: 6px 0; padding-left: 22px; }
        li { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>{{ $form->title }}</h1>
    <p class="text">{{ $form->description }}</p>
    <p class="metadata">Formulário #{{ $form->id }} · Datas em UTC</p>
    @forelse ($submissions as $submission)
        <div class="submission">
            <h2>Envio #{{ $submission->id }}</h2>
            <p class="metadata">Enviado em {{ $submission->created_at?->format('d/m/Y H:i:s') }} UTC</p>
            @if ($form->request_contact_email && $submission->contact_email)
                <p class="text">E-mail de contato: {{ $submission->contact_email }}</p>
            @endif
            @php($answers = $submission->answers->keyBy('question_id'))
            @forelse ($form->questions as $question)
                @php($answer = $answers->get($question->id))
                <h3>{{ $question->position }}. {{ $question->prompt }}</h3>
                @if ($question->type === 'discursive')
                    <div class="text">{{ $answer?->text }}</div>
                @elseif ($answer)
                    <ul>
                        @foreach ($answer->options as $option)
                            <li>{{ $option->label }}</li>
                        @endforeach
                    </ul>
                    @if ($question->type === 'single_choice_with_justification')
                        <p><strong>Justificativa:</strong></p>
                        <div class="text">{{ $answer->justification }}</div>
                    @endif
                @endif
            @empty
                <p>Envio sem perguntas.</p>
            @endforelse
        </div>
    @empty
        <p>Nenhuma resposta recebida.</p>
    @endforelse
</body>
</html>
