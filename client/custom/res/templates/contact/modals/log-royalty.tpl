<div class="panel panel-default">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>RI uitkomst *</label>
                    <select name="outcome" class="form-control" required>
                        <option value="">-- Selecteer uitkomst --</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6" data-field="cancellation-action" style="display: none;">
                <div class="form-group">
                    <label>Vervolgactie *</label>
                    <select name="cancellationAction" class="form-control">
                        <option value="">-- Kies actie --</option>
                        <option value="reschedule_now">Direct nieuwe RI inplannen</option>
                        <option value="cancel_stop">Geen nieuwe afspraak (Stoppen)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row" data-field="booking-container" style="display: none;">
            <div class="col-md-6" data-field="calendar-select">
                <div class="form-group">
                    <label>Kies RI Agenda *</label>
                    <select name="selectedCalendar" class="form-control">
                        <option value="">Laden...</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6" data-field="meeting-slot">
                <div class="form-group">
                    <label>Beschikbaar moment *</label>
                    <select name="selectedSlot" class="form-control">
                        <option value="">-- Kies eerst een agenda --</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Aanvullende notitie</label>
                    <textarea name="coachNote" class="form-control" rows="4" placeholder="Voeg een extra notitie toe..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
