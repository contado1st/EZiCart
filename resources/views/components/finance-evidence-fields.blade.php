<label for="{{ $prefix }}-reference">External transaction or cash receipt reference</label>
<input id="{{ $prefix }}-reference" name="reference" required maxlength="120">
<label for="{{ $prefix }}-reason">Verification notes</label>
<textarea id="{{ $prefix }}-reason" name="reason" required minlength="5" maxlength="500"></textarea>
<label for="{{ $prefix }}-evidence">Receipt or transfer proof (JPG, PNG or PDF, up to 5 MB)</label>
<input id="{{ $prefix }}-evidence" type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" required>
