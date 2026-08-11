/* 藏书概览 / 多维统计共用：KPI、条形、趋势、清单（非框架） */
.stats-kpi-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
.stats-two-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
.stats-list-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }

.stats-kpi { border-radius: 14px; box-shadow: none !important; }
.stats-kpi-icon { flex: 0 0 48px; height: 48px; border-radius: 12px; }
.stats-kpi-icon mdui-icon { font-size: 26px; }
.stats-kpi-value { font-size: 1.7rem; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }

.kpi-total .stats-kpi-icon { background: rgba(var(--mdui-color-primary-container), .55); color: rgb(var(--mdui-color-primary)); }
.kpi-finished .stats-kpi-icon { background: rgb(var(--tag-success-bg)); color: rgb(var(--tag-success-fg)); }
.kpi-reading .stats-kpi-icon { background: rgb(var(--tag-info-bg)); color: rgb(var(--tag-info-fg)); }
.kpi-rate .stats-kpi-icon { background: rgb(var(--tag-gold-bg)); color: rgb(var(--tag-gold-fg)); }
.kpi-dusty .stats-kpi-icon { background: rgb(var(--tag-stone-bg)); color: rgb(var(--tag-stone-fg)); }

.bar-label { flex: 0 0 84px; font-size: .82rem; text-align: right; }
.bar-track { flex: 1; height: 14px; border-radius: 99px; background: rgba(var(--mdui-color-outline), .18); overflow: hidden; }
.bar-fill { height: 100%; min-width: 2px; border-radius: 99px; background: rgb(var(--mdui-color-primary)); }
.bar-count { flex: 0 0 36px; font-size: .8rem; font-variant-numeric: tabular-nums; }

.trend { height: 160px; }
.trend-bar { width: 60%; min-height: 2px; border-radius: 6px 6px 0 0; background: rgb(var(--mdui-color-primary)); }
.trend-label { font-size: .65rem; white-space: nowrap; }
.trend-count { font-size: .68rem; font-variant-numeric: tabular-nums; }

.list-cover { flex: 0 0 40px; width: 40px; height: 56px; border-radius: 6px; overflow: hidden; }
.list-row { border-bottom: 1px solid rgba(var(--mdui-color-outline), .12); }
.list-row:last-child { border-bottom: none; }
