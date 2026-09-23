@if($operation->cfm_recipient)
    <div class="form-group">
        <label class="form-label">Получатель</label>
        <div class="form-static">{{ $operation->cfm_recipient }}</div>
    </div>
@endif

@if($operation->cfm_payer)
    <div class="form-group">
        <label class="form-label">Плательщик</label>
        <div class="form-static">{{ $operation->cfm_payer }}</div>
    </div>
@endif

@if($operation->external_cfm_ref)
    <div class="form-group">
        <label class="form-label">№ операции Уровня</label>
        <div class="form-static">{{ $operation->external_cfm_ref }}</div>
    </div>
@endif
