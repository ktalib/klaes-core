{{-- The workflow's dialog (.iwr-*), shared by the tracker's step review and the department / fee desk row actions. --}}
@once
<style>
    .iwr-backdrop { position: fixed; inset: 0; z-index: 1000; background: rgba(15, 23, 42, .5); display: flex; align-items: center; justify-content: center; padding: 16px; }
    .iwr-modal { background: #fff; border-radius: 18px; width: 100%; max-width: 560px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 25px 60px rgba(0, 0, 0, .25); overflow: hidden; }
    .iwr-head { display: flex; gap: 12px; align-items: flex-start; padding: 18px 20px; border-bottom: 1px solid #f3f4f6; }
    .iwr-modal.tone-green .iwr-head { background: linear-gradient(135deg, #f0fdf4, #fff); }
    .iwr-modal.tone-blue .iwr-head { background: linear-gradient(135deg, #eff6ff, #fff); }
    .iwr-modal.tone-pink .iwr-head { background: linear-gradient(135deg, #fdf2f8, #fff); }
    .iwr-modal.tone-red .iwr-head { background: linear-gradient(135deg, #fef2f2, #fff); }
    .iwr-title { font-size: 18px; font-weight: 700; color: #111827; }
    .iwr-close { border: 0; background: transparent; color: #9ca3af; font-size: 18px; cursor: pointer; width: 32px; height: 32px; border-radius: 8px; }
    .iwr-close:hover { background: #f3f4f6; color: #111827; }
    .iwr-body { padding: 18px 20px; overflow-y: auto; }
    .iwr-foot { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 12px 20px; border-top: 1px solid #f3f4f6; background: #fafafa; }
    .iwt-now-label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; }
</style>
@endonce

