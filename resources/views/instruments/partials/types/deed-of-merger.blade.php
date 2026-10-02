<div class="grid grid-cols-2 gap-4">
    <x-instrument-input id="mergerDate" label="Merger Date" type="date" icon="calendar" />
    <x-instrument-input id="effectiveDate" label="Effective Date" type="date" icon="calendar" />
    <x-instrument-input id="newEntityName" label="New Entity Name" icon="briefcase"
        placeholder="Enter name of the merged entity" />
    <x-instrument-textarea id="mergedEntities" label="Merged Entities" icon="users"
        placeholder="List entities involved in the merger" class="w-full" rows="1" />
</div>