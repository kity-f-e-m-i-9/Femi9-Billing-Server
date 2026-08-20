<style>
    .po-eta-banner {
        background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;
        border-radius: 8px; padding: 10px 16px; margin-bottom: 16px;
        overflow: hidden;
    }
    .po-eta-banner-track {
        overflow: hidden;
        width: 100%;
        white-space: nowrap;
    }
    .po-eta-banner-track span {
        display: inline-block;
        white-space: nowrap;
        padding-left: 100%;
        will-change: transform;
        animation: po-eta-scroll 14s linear infinite;
        font-weight: 600; font-size: 13.5px;
    }
    .po-eta-banner-track span i {
        font-size: 18px;
        vertical-align: middle;
        margin-right: 6px;
        margin-top: -3px;
        display: inline-block;
        transform: scaleX(-1);
    }
    @keyframes po-eta-scroll {
        0%   { transform: translateX(0); }
        100% { transform: translateX(-100%); }
    }
</style>
