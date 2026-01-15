define('custom:views/fields/survey-data', ['views/fields/text'], (TextFieldView) => {
    return class extends TextFieldView {

        afterRender() {
            super.afterRender();
            let val = this.model.get(this.name);

            if (!val) {
                this.$el.html('<span class="text-muted">Geen gegevens</span>');
                return;
            }

            let data = JSON.parse(val);
            let html = '<div class="survey-data-container" style="background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">';
            html += '<table class="table table-condensed" style="margin-bottom: 0;">';

            for (let key in data) {
                let value = data[key];

                if (!value) {
                    continue;
                }

                if (Array.isArray(value)) {
                    value = value.join(', ');
                }

                html += `<tr>
                        <td style="width: 40%; font-weight: bold; border-top: none;">${key}</td>
                        <td style="border-top: none;">${value}</td>
                    </tr>`;
            }

            html += '</table></div>';
            this.$el.html(html);
        }
    }
});