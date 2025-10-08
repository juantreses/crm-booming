<div class="panel panel-default">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Kickstart Uitkomst *</label>
                    <select name="outcome" class="form-control" required>
                        <option value="">-- Selecteer uitkomst --</option>
                        <option value="became_client">Klant geworden</option>
                        <option value="no_show">Niet opgedaagd</option>
                        <option value="not_converted">Geen klant geworden</option>
                        <option value="still_thinking">Twijfel</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Kickstart datum/tijd</label>
                    <input type="datetime-local" name="kickstartDateTime" class="form-control" />
                    <small class="form-text text-muted">Laat leeg voor huidige tijd</small>
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