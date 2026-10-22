{{-- One instrument form template per type, plus the sidebar templates.
     Shared by the capture page (instruments/create) and Edit (instruments/edit)
     so Edit always opens the same form the record was captured on. --}}
<!-- Templates for Instrument Types -->
<template id="template-power-of-attorney">
    @include('instruments.partials.types.simple-duration')
</template>
<template id="template-irrevocable-power-of-attorney">
    @include('instruments.partials.types.simple-duration')
</template>
<template id="template-occupancy-permit">
    @include('instruments.partials.types.occupancy-permit')
</template>
<template id="template-certificate-of-occupancy">
    @include('instruments.partials.types.certificate-of-occupancy')
</template>
<template id="template-deed-of-mortgage">
    @include('instruments.partials.types.deed-of-mortgage')
</template>
<template id="template-tripartite-mortgage">
    @include('instruments.partials.types.tripartite-mortgage')
</template>
<template id="template-deed-of-assignment">
    @include('instruments.partials.types.deed-of-assignment')
</template>
<template id="template-deed-of-lease">
    @include('instruments.partials.types.deed-of-lease')
</template>
<template id="template-deed-of-sub-lease">
    @include('instruments.partials.types.deed-of-sub-lease')
</template>
<template id="template-deed-of-sub-division">
    @include('instruments.partials.types.deed-of-sub-division')
</template>
<template id="template-deed-of-merger">
    @include('instruments.partials.types.deed-of-merger')
</template>
<template id="template-deed-of-surrender-release">
    @include('instruments.partials.types.deed-of-surrender-release')
</template>
<template id="template-devolution-order">
    @include('instruments.partials.types.devolution-order')
</template>
<template id="template-deed-of-gift">
    @include('instruments.partials.types.deed-of-gift')
</template>
<!-- Sidebar Templates -->
<template id="template-deed-of-mortgage-sidebar">
    @include('instruments.partials.types.sidebars.deed-of-mortgage')
</template>
<template id="template-tripartite-mortgage-sidebar">
    @include('instruments.partials.types.sidebars.tripartite-mortgage')
</template>
<template id="template-deed-of-surrender-release-sidebar">
    @include('instruments.partials.types.sidebars.deed-of-surrender-release')
</template>
<template id="template-deed-of-assignment-sidebar">
    @include('instruments.partials.types.sidebars.deed-of-assignment')
</template>
<template id="template-deed-of-gift-sidebar">
    @include('instruments.partials.types.sidebars.deed-of-assignment')
</template>
<template id="template-certificate-of-occupancy-sidebar">
    @include('instruments.partials.types.sidebars.deed-of-assignment')
</template>
<template id="template-power-of-attorney-sidebar">
    @include('instruments.partials.types.sidebars.solicitor_toggle')
</template>
<template id="template-irrevocable-power-of-attorney-sidebar">
    @include('instruments.partials.types.sidebars.solicitor_toggle')
</template>
