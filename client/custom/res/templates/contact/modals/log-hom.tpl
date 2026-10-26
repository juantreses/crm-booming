<div class="panel panel-default">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>HOM uitkomst *</label>
                    <select name="outcome" class="form-control" required>
                        <option value="">-- Selecteer uitkomst --</option>
                        <option value="attended">Aanwezig</option>
                        <option value="no_show">Niet opgedaagd</option>
                        <option value="cancelled">Geannuleerd</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Spaarkaart saldo</label>
                    <p class="form-control-static"><span data-role="savings-balance">0</span> punten</p>
                </div>
            </div>
        </div>

        <div class="row" data-field="cancellation-action" style="display: none;">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Vervolgactie na annulering *</label>
                    <select name="cancellationAction" class="form-control">
                        <option value="">-- Kies actie --</option>
                        <option value="reschedule_now">Direct nieuwe datum inplannen</option>
                        <option value="cancel_stop">Geen nieuwe afspraak (Stoppen)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row" data-field="hom-rating" style="display: none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label>HOM-uitkomst *</label>
                    <select name="homRating" class="form-control">
                        <option value="">-- Kies uitkomst --</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                    <small class="form-text text-muted">A: 40 punten inwisselen, B: PCX of distributeur, C/D: RI</small>
                </div>
            </div>
            <div class="col-md-6" data-field="client-type" style="display: none;">
                <div class="form-group">
                    <label>Klant wordt *</label>
                    <select name="clientType" class="form-control">
                        <option value="">-- Kies --</option>
                        <option value="pcx_15">PCX</option>
                        <option value="distributor">Distributeur</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6" data-field="redeem-info" style="display: none;">
                <div class="form-group">
                    <label>Spaarkaart</label>
                    <p class="form-control-static">40 punten worden afgetrokken van het saldo.</p>
                </div>
            </div>
        </div>

        <div class="row" data-field="booking-container" style="display: none;">
            <div class="col-md-12">
                <label data-role="booking-label">Afspraak *</label>
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
            <div class="col-md-12">
                <div class="form-group">
                    <label>Aanvullende notitie</label>
                    <textarea name="coachNote" class="form-control" rows="4" placeholder="Voeg een extra notitie toe..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
