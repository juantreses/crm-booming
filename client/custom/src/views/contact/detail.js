define('custom:views/contact/detail', ['views/detail'], function (Dep) {
    
    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            
            // Listen for after:render event to calculate and display age
            this.listenTo(this.model, 'sync', () => {
                this.displayAge();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.displayAge();
        },

        displayAge: function () {
            const dateOfBirth = this.model.get('cDateOfBirth');
            
            if (!dateOfBirth) {
                return;
            }

            const age = this.calculateAge(dateOfBirth);
            
            // Find the cDateOfBirth field and add age next to it
            const $dateOfBirthField = this.$el.find('.field[data-name="cDateOfBirth"]');
            
            if ($dateOfBirthField.length) {
                // Remove any existing age display
                $dateOfBirthField.find('.age-display').remove();
                
                // Add age display
                $dateOfBirthField.append(
                    $('<span>')
                        .addClass('age-display')
                        .css({
                            'margin-left': '10px',
                            'color': '#999',
                            'font-style': 'italic'
                        })
                        .text('(' + age + ')')
                );
            }
        },

        calculateAge: function (dateOfBirth) {
            const birthDate = new Date(dateOfBirth);
            const today = new Date();
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            // Adjust age if birthday hasn't occurred yet this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }

    });
});