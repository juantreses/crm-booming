define(['action-handler'], (Dep) => {

    return class extends Dep {

        async bookWorkout(data, e) {
            this.view.disableMenuItem('bookWorkout');

            this.view.createView('dialog', 'custom:views/shared/modals/book-workout', {
                model: this.view.model
            }, (view) => {
                view.render();

                view.once('success', () => {
                    this.view.model.fetch();
                    this.view.render();

                    Espo.Ui.success('Workout geboekt.');
                });

                view.once('close', () => {
                    this.view.enableMenuItem('bookWorkout');
                });
            });
        }

        isBookWorkoutVisible() {
            return true;
        }
    }
});
