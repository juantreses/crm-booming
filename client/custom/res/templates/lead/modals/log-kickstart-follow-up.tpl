<div class="panel panel-default">
	<div class="panel-body">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
					<label>Bericht uitkomst *</label>
					<select name="outcome" class="form-control" required>
						<option value="">-- Selecteer uitkomst --</option>
                        <option value="converted">Klant geworden</option>
                        <option value="not_converted">Geen klant geworden</option>
					</select>
				</div>
			</div>
			<div class="col-md-6">
                <div class="form-group">
                    <label>Opvolging datum/tijd</label>
                    <input type="datetime-local" name="followUpDateTime" class="form-control" />
                    <small class="form-text text-muted">Laat leeg voor huidige tijd</small>
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
