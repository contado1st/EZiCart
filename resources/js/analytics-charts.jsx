import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

const number = (value, money = false) => new Intl.NumberFormat('en-PH', { maximumFractionDigits: money ? 2 : 0, ...(money ? { style: 'currency', currency: 'PHP' } : {}) }).format(value);
const palette = ['#be2854', '#71384f', '#417d87', '#8061a3', '#a47724', '#32694a', '#7a596b'];

function Trend({ data, commercial }) {
    const [metric, setMetric] = useState('orders');
    const [active, setActive] = useState(0);
    const maximum = Math.max(1, ...data.map(point => point[metric]));
    const x = index => 48 + index * 640 / Math.max(1, data.length - 1);
    const y = value => 240 - value / maximum * 190;
    const path = data.map((point, index) => `${index === 0 ? 'M' : 'L'}${x(index)},${y(point[metric])}`).join(' ');
    const current = data[Math.min(active, data.length - 1)];
    const labels = { orders: 'Orders placed', completed: 'Completed orders', delivered: 'Orders with recorded delivery', net: 'Net seller sales (PHP)' };
    return <section className="analytics-chart analytics-trend"><header><div><h2>Performance over time</h2><p>Current recorded outcomes grouped by order placement date.</p></div><label className="analytics-chart-select">Measure<select value={metric} onChange={event => { setMetric(event.target.value); setActive(0); }}>{Object.entries(labels).filter(([key]) => commercial || key !== 'net').map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label></header>
        <svg className="analytics-line-chart" viewBox="0 0 720 290" role="img" tabIndex="0" aria-label={`${labels[metric]}. Use left and right arrow keys to inspect periods.`} onKeyDown={event => { if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') { event.preventDefault(); setActive(index => Math.max(0, Math.min(data.length - 1, index + (event.key === 'ArrowRight' ? 1 : -1)))); } }}>
            {[0, .25, .5, .75, 1].map(fraction => <g key={fraction}><line x1="48" y1={y(maximum * fraction)} x2="688" y2={y(maximum * fraction)} stroke="#e7e9ef" strokeDasharray="3 5"/><text x="40" y={y(maximum * fraction) + 4} textAnchor="end">{new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(maximum * fraction)}</text></g>)}
            <path key={metric} className="analytics-trend-line" d={path} fill="none" stroke="currentColor" strokeWidth="3" pathLength="1"/>
            {data.map((point, index) => <circle key={point.period} cx={x(index)} cy={y(point[metric])} r={index === active ? 5 : 3} className="analytics-trend-dot" onMouseEnter={() => setActive(index)}><title>{point.period}: {number(point[metric], metric === 'net')}</title></circle>)}
            <text x="48" y="272">{data[0]?.period}</text><text x="688" y="272" textAnchor="end">{data[data.length - 1]?.period}</text>
        </svg><p className="analytics-chart-readout" aria-live="polite">{current?.period}: <strong>{number(current?.[metric] || 0, metric === 'net')}</strong> {labels[metric]}</p>
    </section>;
}

function Status({ data }) {
    const total = data.reduce((sum, point) => sum + point.value, 0);
    let offset = 0;
    return <section className="analytics-chart"><header><div><h2>Orders by status</h2><p>Current lifecycle state of the selected orders.</p></div></header><div className="analytics-status-layout"><svg viewBox="0 0 160 160" role="img" aria-label={total ? `${total} orders. ${data.map(point => `${point.label}: ${point.value}`).join(', ')}` : 'No orders in the selected period'}>
        <circle cx="80" cy="80" r="57" fill="none" stroke="#e7e9ef" strokeWidth="18"/>
        {data.map((point, index) => { const share = point.value / total * 100; const start = offset; offset += share; return <circle key={point.label} cx="80" cy="80" r="57" pathLength="100" stroke={palette[index % palette.length]} strokeWidth="18" fill="none" strokeDasharray={`${share} ${100 - share}`} strokeDashoffset={-start} transform="rotate(-90 80 80)"><title>{point.label}: {point.value} ({share.toFixed(1)}%)</title></circle>; })}
        <text x="80" y="80" textAnchor="middle" className="analytics-donut-total">{number(total)}</text><text x="80" y="98" textAnchor="middle">orders</text>
        </svg><ul>{data.length ? data.map((point, index) => <li key={point.label}><span className="analytics-chart-key" style={{ backgroundColor: palette[index % palette.length] }}/><span>{point.label}</span><strong>{point.value}</strong><small>{(point.value / total * 100).toFixed(1)}%</small></li>) : <li>No matching orders.</li>}</ul></div></section>;
}

function Ranking({ groups }) {
    const [selected, setSelected] = useState(groups.some(group => group.key === 'products') ? 'products' : groups[0]?.key);
    const group = groups.find(item => item.key === selected) || groups[0];
    const rows = group?.rows || [];
    const valueKey = group?.key === 'products' || group?.key === 'categories' ? 'Gross PHP' : group?.key === 'sellers' ? 'Net seller sales PHP' : 'Orders';
    const maximum = Math.max(1, ...rows.map(row => Number(row[valueKey]) || 0));
    return <section className="analytics-chart analytics-ranking"><header><div><h2>Performance ranking</h2><p>Top ten by the measure shown; exports contain all rows.</p></div><label className="analytics-chart-select">Breakdown<select value={selected} onChange={event => setSelected(event.target.value)}>{groups.map(item => <option key={item.key} value={item.key}>{item.title}</option>)}</select></label></header>
        {rows.length ? <ol>{rows.map((row, index) => <li key={`${selected}-${index}`}><div><span>{String(Object.values(row)[0])}</span><strong>{number(row[valueKey], valueKey.endsWith('PHP'))}</strong></div><div className="analytics-bar-track"><span className="analytics-bar" style={{ width: `${Math.max(0, Number(row[valueKey]) / maximum * 100)}%` }}/></div></li>)}</ol> : <p className="analytics-empty">No qualifying records for this breakdown.</p>}
        <p className="analytics-chart-caption">Measure: {valueKey}. Rankings describe this measure only.</p>
    </section>;
}

const mount = document.querySelector('[data-analytics-charts]');
const source = document.getElementById('analytics-chart-data');
if (mount && source) {
    try { const data = JSON.parse(source.textContent); createRoot(mount).render(<div className="analytics-charts-grid"><Trend data={data.trend} commercial={data.commercial}/><Status data={data.statuses}/><Ranking groups={data.groups}/></div>); }
    catch { mount.textContent = 'Interactive charts are unavailable. Use the report tables and exports below.'; }
}
