<script setup lang="ts">
import { Color, TextStyle } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Editor } from '@tiptap/vue-3';
import { onBeforeUnmount, onMounted } from 'vue';
import { docToSpans, spansToDoc } from '@/lib/richText';
import type { TextSpan } from '@/types/book';

const props = defineProps<{
    spans: TextSpan[];
}>();

const emit = defineEmits<{
    change: [spans: TextSpan[]];
    ready: [editor: Editor];
    done: [];
}>();

const editor = useEditor({
    content: spansToDoc(props.spans),
    extensions: [
        StarterKit.configure({
            heading: false,
            blockquote: false,
            bulletList: false,
            orderedList: false,
            listItem: false,
            listKeymap: false,
            codeBlock: false,
            code: false,
            horizontalRule: false,
            strike: false,
            underline: false,
            link: false,
            dropcursor: false,
            gapcursor: false,
        }),
        TextStyle,
        Color,
    ],
    editorProps: {
        attributes: {
            class: 'outline-none',
            'aria-label': 'Texto del cuadro',
        },
        handleKeyDown: (_view, event) => {
            if (event.key === 'Escape') {
                emit('done');

                return true;
            }

            return false;
        },
    },
    onUpdate: ({ editor: e }) => emit('change', docToSpans(e.getJSON())),
});

onMounted(() => {
    const e = editor.value;

    if (e) {
        e.commands.focus('end');
        emit('ready', e);
    }
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<template>
    <EditorContent
        :editor="editor"
        class="noctua-rich-text size-full cursor-text"
        @pointerdown.stop
    />
</template>

<style>
.noctua-rich-text .ProseMirror {
    min-height: 100%;
    white-space: pre-wrap;
}

.noctua-rich-text .ProseMirror p {
    margin: 0;
}
</style>
