<div class="panel panel-default">
	<div class="panel-body">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					<label>Bericht uitkomst *</label>
					<select name="outcome" class="form-control" required>
						<option value="">-- Selecteer uitkomst --</option>
                        <option value="invited">Afspraak ingepland</option>
                        <option value="call_again">Opnieuw bellen</option>
                        <option value="not_interested">Geen interesse</option>
					</select>
				</div>
			</div>
            <div class="col-md-6" data-field="calendar-select" style="display: none;">
                <div class="form-group">
                    <label>Kies Agenda *</label>
                    <select name="selectedCalendar" class="form-control">
                        <option value="">Laden...</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6" data-field="meeting-slot" style="display: none;">
                <div class="form-group">
                    <label>Beschikbaar moment *</label>
                    <select name="selectedSlot" class="form-control">
                        <option value="">-- Kies eerst een agenda --</option>
                    </select>
                </div>
            </div>
			<div class="col-md-6" data-field="call-again-date" style="display: none;">
				<div class="form-group">
					<label>Datum/tijd opnieuw bellen *</label>
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
