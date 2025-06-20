<template>
  <k-field class="k-applemusic-field" v-bind="$props">
    <k-button icon="edit-line" size="xs" slot="options" variant="filled" @click="openDrawer">Edit...</k-button>
    <k-html-field-preview class="k-applemusic-preview" :value="value" />
  </k-field>
</template>

<script>
export default {
    extends: "k-field",
    props: {
        value: String
    },
    methods: {
        openDrawer() {
            this.$panel.drawer.open({
                component: 'k-form-drawer',
                props: {
                    icon: 'music',
                    title: this.label || 'Apple Music Embed',
                    value: {
                        [this.name]: this.value
                    },
                    fields: {
                        [this.name]: {
                            label: 'Embed Code',
                            type: 'textarea',
                            icon: 'code',
                            buttons: false,
                            font: 'monospace',
                            spellcheck: false,
                            size: 'medium',
                            help: this.help  || 'Copy and paste the embed code for a album, song, or playlist from Apple Music into the field.'
                        }
                    }
                },
                on: {
                    submit: (formData) => {
                      const newValue = formData[this.name];
                      this.$emit('input', newValue); // updates field value
                      this.$emit('change', newValue); // triggers page dirty state
                      this.closeDrawer();
                    }
                }
            });
        },
        closeDrawer() {
            this.$panel.drawer.close();
        }
    }
};
</script>

<style>
.k-applemusic-preview {
    background: #fff;
    border-radius: var(--input-rounded);
    padding: 0;
}
</style>