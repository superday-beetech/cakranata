<script setup lang="ts">
import { useRouter } from "vue-router";
import { useOnlineStatus } from "@/composables/useOnlineStatus";

const router = useRouter();
const { isOnline } = useOnlineStatus();

const goBack = () => {
  if (window.history.length > 1) router.back();
  else router.replace("/");
};

const retry = () => {
  if (isOnline.value) goBack();
};
</script>
