{{--
    Filter bar shared by the SF and PF database pages.
    Expects: $action, $numberLabel, $filters, $filterErrors, $usernames, $order, $hasFilters
    A plain GET form, so filtered views are bookmarkable and pagination keeps them (withQueryString).
--}}
<form method="GET" action="{{ $action }}" class="card filter-bar" role="search" aria-label="Filter {{ $numberLabel }} records">
    @if ($order === 'desc')
        <input type="hidden" name="order" value="desc">
    @endif

    <div class="filter-field">
        <label for="filter-date-from">Create Date from</label>
        <div class="date-input" data-date-input>
            <input type="text" id="filter-date-from" name="date_from" value="{{ $filters['date_from'] }}"
                   placeholder="YYYY-MM-DD" maxlength="10" inputmode="numeric" autocomplete="off">
            <button type="button" class="date-picker-btn" data-date-picker aria-label="Pick a date" title="Pick a date">&#128197;</button>
            <input type="date" class="date-native" tabindex="-1" aria-hidden="true" min="1900-01-01" max="2999-12-31">
        </div>
    </div>

    <div class="filter-field">
        <label for="filter-date-to">Create Date to</label>
        <div class="date-input" data-date-input>
            <input type="text" id="filter-date-to" name="date_to" value="{{ $filters['date_to'] }}"
                   placeholder="YYYY-MM-DD" maxlength="10" inputmode="numeric" autocomplete="off">
            <button type="button" class="date-picker-btn" data-date-picker aria-label="Pick a date" title="Pick a date">&#128197;</button>
            <input type="date" class="date-native" tabindex="-1" aria-hidden="true" min="1900-01-01" max="2999-12-31">
        </div>
    </div>

    <div class="filter-field">
        <label for="filter-user">User</label>
        <select id="filter-user" name="user">
            <option value="">All users</option>
            @foreach ($usernames as $username)
                <option value="{{ $username }}" @selected($filters['user'] === $username)>{{ $username }}</option>
            @endforeach
        </select>
    </div>

    <div class="filter-field filter-field-grow">
        <label for="filter-number">{{ $numberLabel }} Number</label>
        <input type="search" id="filter-number" name="number" value="{{ $filters['number'] }}"
               placeholder="Search {{ $numberLabel }} Number" maxlength="30" inputmode="numeric" autocomplete="off">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        @if ($hasFilters)
            <a class="btn" href="{{ $action }}{{ $order === 'desc' ? '?order=desc' : '' }}">Clear</a>
        @endif
    </div>

    @if ($filterErrors->isNotEmpty())
        <div class="filter-errors" role="alert">
            @foreach ($filterErrors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif
</form>
