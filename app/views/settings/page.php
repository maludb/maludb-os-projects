<?php /** The Projects settings screen: header + the form. Data: s (settings row, point_scale as ints), errors */ ?>
<div class="page-header" id="settings-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Settings</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Settings</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/settings/tokens" id="settings-tokens-btn" class="btn btn-light-brand" hx-get="/settings/tokens" hx-target="#page-content" hx-push-url="/settings/tokens"><i class="feather-key me-2"></i><span>Tokens</span></a>
                <a href="/activity" id="settings-activity-btn" class="btn btn-light-brand" hx-get="/activity" hx-target="#page-content" hx-push-url="/activity"><i class="feather-activity me-2"></i><span>Activity</span></a>
                <button type="submit" form="settings-form" id="settings-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="settings-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="settings-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <?= view('settings/form.php', ['s' => $s]) ?>
    </div></div></div></div>
</div>
