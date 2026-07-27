<div class="d-flex flex-column justify-content-between align-items-center float-start mt-5" style="width: 600px;color: #fff;">
  <img src="{$MEDIAURL}/{$config.logo}" alt="" width="200">
  <h1 class="mt-2 fw-bolder">{$config.sitename|clean}</h1>
  <div class="row mt-5 text-center gx-5">
    <div class="col-12 fs-4 p-2">
      <p class="mt-2">
          {$config.address1|clean} - {$config.address2|clean}
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-geo-alt-fill" viewBox="0 0 16 16">
          <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
        </svg>
      </p>
    </div>
    <div class="col-4 offset-2 fs-5 p-2 d-flex flex-row justify-content-evenly align-items-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone-fill" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
      </svg> {$config.phone|clean}
    </div>
    <div class="col-4 fs-5 p-2 d-flex flex-row justify-content-evenly align-items-center">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-phone-fill" viewBox="0 0 16 16">
        <path d="M3 2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V2zm6 11a1 1 0 1 0-2 0 1 1 0 0 0 2 0z"/>
      </svg> {$config.mobile|clean}
    </div>
  </div>
</div>
