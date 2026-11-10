<script setup>
// import { inject } from "vue";
// const baseUrl = inject("baseUrl");
// console.info(baseUrl());
</script>

<template>
  <div class="card bg-info-subtle shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
      <div class="row align-items-center">
        <div class="col-9">
          <h4 class="fw-semibold mb-8">Basic Table</h4>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <a class="text-muted text-decoration-none" href="../main/index.html"> Home </a>
              </li>
              <li class="breadcrumb-item" aria-current="page">Basic Table</li>
            </ol>
          </nav>
        </div>

        <div class="col-3">
          <div class="text-center mb-n5">
            <img :src="baseUrl('uploads/ChatBc.png')" alt="modernize-img" class="img-fluid mb-n4" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
