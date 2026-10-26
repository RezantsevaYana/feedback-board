@props(['url'])
{{-- The brand tag with the slanted edge (.slant on the board) and the wordmark; the slant is drawn with borders, see the theme --}}
<tr>
<td class="header">
<table class="header-inner" align="center" width="560" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="brand" target="_blank" rel="noopener">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-slant"></td>
<td class="brand-tag">FB</td>
<td class="brand-name">{{ $slot }}</td>
</tr>
</table>
</a>
</td>
</tr>
</table>
</td>
</tr>