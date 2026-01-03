@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
[<span style="margin: 0 4px;">{!! $slot !!}</span>]
</a>
</td>
</tr>
