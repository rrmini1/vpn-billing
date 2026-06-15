<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
});
</script>

<template>
    <div class="metric-tile">
        <div class="metric-label">{{ label }}</div>
        <div class="metric-value">{{ value }}</div>
    </div>
</template>

<style scoped>
.metric-tile {
    min-height: 76px;
    padding: 12px;
    border: 1px solid #dfe5ea;
    border-radius: 8px;
    background: #fbfcfe;
}

.metric-label {
    color: #61717d;
    font-size: 13px;
}

.metric-value {
    margin-top: 8px;
    color: #18252f;
    font-size: 18px;
    font-weight: 700;
    overflow-wrap: anywhere;
}
</style>
