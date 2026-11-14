<script setup lang="ts">
import { offlineDb } from "@/offline/database/offlineDatabase";

await offlineDb.open();

console.log({
  database: offlineDb.name,
  version: offlineDb.verno,
  tables: offlineDb.tables.map((table) => table.name),
});
</script>
<template></template>
