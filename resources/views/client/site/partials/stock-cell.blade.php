{{-- Outlet visit stock: current stock + date it was received. Expects $v (SiteVisit). --}}
<td class="text-nowrap">
    {{ $v->stockText() }}
    @if ($v->date_of_stock_received)<div class="text-muted small">Received {{ $v->date_of_stock_received->format('d M Y') }}</div>@endif
</td>
