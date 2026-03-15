<div class="panel panel-default">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label data-role="intro-outcome-label">Intro uitkomst *</label>
                    <select name="outcome" class="form-control" required>
                        <option value="">-- Selecteer uitkomst --</option>
                        <option value="attended">Bijgewoond</option>
                        <option value="no_show">Niet opgedaagd</option>
                        <option value="cancelled">Geannuleerd</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label data-role="intro-date-label">Intro datum/tijd</label>
                    <input type="datetime-local" name="introDateTime" class="form-control" />
                    <small class="form-text text-muted">Laat leeg voor huidige tijd</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12" data-field="cancellation-action" style="display: none; margin-top: 15px;">
                <div class="form-group">
                    <label>Vervolgactie na annulering *</label>
                    <select name="cancellationAction" class="form-control">
                        <option value="">-- Kies actie --</option>
                        <option value="reschedule_now">Direct nieuwe datum inplannen</option>
                        <option value="reschedule_later">Nog geen datum (Terugbellen)</option>
                        <option value="cancel_stop">Geen nieuwe afspraak (Stoppen)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row" data-field="book-next-meeting" style="display: none;">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Volgende afspraak</label>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="bookNextMeeting" value="1" />
                            Direct volgende afspraak inplannen
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        Je logt een <span data-role="intro-context-label">Intro</span> en kan meteen een volgende afspraak plannen.
                    </small>
                </div>
            </div>
        </div>

        <div class="row" data-field="booking-container" style="display: none;">
            <div class="col-md-12">
                <small class="form-text text-muted" data-role="booking-hint"></small>
            </div>
            <div class="col-md-6" data-field="calendar-select">
                <div class="form-group">
                    <label>Kies Agenda *</label>
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
            <div class="col-md-6" data-field="call-again-date" style="display: none;">
                <div class="form-group">
                    <label>Datum/tijd opnieuw bellen</label>
                    <input type="datetime-local" name="callAgainDateTime" class="form-control" />
                    <small class="form-text text-muted">Vul in wanneer opnieuw te bellen</small>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label>Aanvullende notitie</label>
                    <textarea name="coachNote" class="form-control" rows="4" placeholder="Voeg een extra notitie toe..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
