define('custom:views/cteam/record/panels/downlines', ['views/record/panels/bottom'], (BottomPanelView) => {
    return class extends BottomPanelView {

	templateContent = `
	    <div class="downlines-panel"></div>
	`;

        setup() {
            super.setup();
            this.tree = [];
            this.loadDownlines();
        }

        loadDownlines() {
            const id = this.model.id;

            Espo.Ajax.getRequest('downlines', { id: id, maxDepth: 6 })
		 .then(tree => {
                    this.tree = tree;
                    this.reRender();
                });
        }

        data() {
            return {
                tree: this.tree
            };
        }

        // recursieve helper om nested lijst te bouwen
        renderTree(nodes) {
            if (!nodes || nodes.length === 0) return '';

            let html = '<ul>';
            nodes.forEach(node => {
                const url = '#CTeam/view/' + node.id;
		html += `<li><a href="${url}">${_.escape(node.name)}</a>`;
                if (node.children && node.children.length) {
                    html += this.renderTree(node.children);
                }
                html += '</li>';
            });
            html += '</ul>';
            return html;
        }

        afterRender() {
            if (this.tree && this.tree.length) {
                this.$el.find('.downlines-panel').html(this.renderTree(this.tree));
            } else {
                this.$el.find('.downlines-panel').html('<i>No downlines found</i>');
            }
        }

    };
});

