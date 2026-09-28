<?php /** My work. Data: rows, tab, counts, tz, resultsHtml */ ?>
<div class="page-header" id="my-work-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">My work</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li>
            <li class="breadcrumb-item">My work</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/filters" id="my-work-filters-btn" class="btn btn-light-brand" hx-get="/filters" hx-target="#page-content" hx-push-url="/filters"><i class="feather-bookmark me-2"></i><span>Saved filters</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="my-work-content">
    <div class="card stretch stretch-full" id="my-work-card">
        <?= $resultsHtml ?>
    </div>
</div>
