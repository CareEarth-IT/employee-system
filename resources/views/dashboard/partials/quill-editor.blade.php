@props([
    'editorId' => 'content_html_editor',
    'textareaId' => 'content_html',
    'initialHtml' => '',
    'department' => '社員共通',
    'uploadUrl' => route('dashboard.content.images.store'),
    'height' => 420,
])

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
    .dashboard-quill .ql-container {
        min-height: {{ (int) $height }}px;
        font-size: 14px;
    }
    .dashboard-quill .ql-editor {
        min-height: {{ (int) $height }}px;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const editorElement = document.getElementById(@json($editorId));
    const textarea = document.getElementById(@json($textareaId));
    if (!editorElement || !textarea || typeof Quill === 'undefined') return;

    const csrf = @json(csrf_token());
    const uploadUrl = @json($uploadUrl);
    const department = @json($department);

    const quill = new Quill(editorElement, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: imageHandler,
                },
            },
        },
        placeholder: 'お知らせを入力してください…',
    });

    if (textarea.value.trim() !== '') {
        quill.clipboard.dangerouslyPasteHTML(textarea.value);
    }

    const syncToTextarea = () => {
        textarea.value = quill.root.innerHTML;
    };

    quill.on('text-change', syncToTextarea);

    const form = textarea.closest('form');
    if (form) {
        form.addEventListener('submit', syncToTextarea);
    }

    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('image', file);
            formData.append('department', department);
            formData.append('_token', csrf);

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    let message = 'Upload failed';
                    try {
                        const errorData = await response.json();
                        message = errorData.message || message;
                    } catch (parseError) {
                        // ignore JSON parse errors
                    }
                    throw new Error(message);
                }

                const data = await response.json();
                if (!data.location) {
                    throw new Error('Invalid response');
                }

                const range = quill.getSelection(true) ?? { index: quill.getLength(), length: 0 };
                quill.insertEmbed(range.index, 'image', data.location, Quill.sources.USER);
                quill.setSelection(range.index + 1);
                syncToTextarea();
            } catch (error) {
                const message = error instanceof Error && error.message
                    ? error.message
                    : '画像のアップロードに失敗しました。';
                window.alert(message);
            }
        });
    }
});
</script>
@endpush
