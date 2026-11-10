<!-- Sidebar Start -->
<aside class="left-sidebar with-vertical">
    <div><!-- ---------------------------------- -->
        <!-- Start Vertical Layout Sidebar -->
        <!-- ---------------------------------- -->
        <div class="brand-logo d-flex align-items-center justify-content-between">
            <a href="../main/index.html" class="text-nowrap logo-img">
                <img src="<?= get_upload_url('uploads/settings/' . app_setting('logoHeaderAdmin')) ?>"
                    class="dark-logo" alt="Logo-Dark" style="max-height:50px;" />
                <img src="<?= get_upload_url('uploads/settings/' . app_setting('logoHeaderAdmin')) ?>"
                    class="light-logo" alt="Logo-Dark" width="50" />
            </a>
            <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none">
                <i class="ti ti-x"></i>
            </a>
        </div>

        <nav class="sidebar-nav scroll-sidebar" data-simplebar>
            <?= $backendMenu ?? ''; ?>

        </nav>

        <div class="fixed-profile p-3 mx-4 mb-2 bg-secondary-subtle rounded mt-3">
            <div class="hstack gap-3">
                <div class="john-img">
                    <?php echo userAvatar('rounded-circle" width="40" height="40', 'avatar'); ?>
                </div>
                <div class="john-title">
                    <h6 class="mb-0 fs-4 fw-semibold">
                        <?= currentUserFullName(); ?>
                    </h6>
                    <!-- <span class="fs-2">Designer</span> -->
                </div>
                <button class="border-0 bg-transparent text-primary ms-auto" tabindex="0" type="button"
                    aria-label="logout" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="logout"
                    id="logout">
                    <i class="ti ti-power fs-6"></i>
                </button>
            </div>
        </div>

        <!-- ---------------------------------- -->
        <!-- Start Vertical Layout Sidebar -->
        <!-- ---------------------------------- -->
    </div>
</aside>
