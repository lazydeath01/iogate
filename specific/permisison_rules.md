# Permissions

The `departments` table, `roles` table and `persons` table have a `permission` column of type `JSON`.

If no permissions have been granted to a department or role, the `permission` column remains `null`.

The `permission` field contains a JSON object with a list of constraints:

* `{}` means there are **no constraints**. People with this permission can enter or exit through any gate at any time.
* `null` means people **cannot pass through any gate**.

## Constraint List

### 1. `allowed_date`

People can enter or stay within the specified date ranges. Multiple `allowed_date` ranges are supported.

```json
"allowed_date": [
  {
    "start": "dd/mm/yyyy",
    "end": "dd/mm/yyyy"
  },
  {
    "start": "dd/mm/yyyy",
    "end": "dd/mm/yyyy"
  }
]
```

### 2. `excluded_date`

People cannot enter or stay within the specified date ranges. Multiple `excluded_date` ranges are supported.

```json
"excluded_date": [
  {
    "start": "dd/mm/yyyy",
    "end": "dd/mm/yyyy"
  },
  {
    "start": "dd/mm/yyyy",
    "end": "dd/mm/yyyy"
  }
]
```

### 3. `allowed_time`

People can enter within the specified time ranges. Multiple `allowed_time` ranges are supported.

```json
"allowed_time": [
  {
    "start": "hh:mm",
    "end": "hh:mm"
  },
  {
    "start": "hh:mm",
    "end": "hh:mm"
  }
]
```

### 4. `excluded_time`

People cannot enter within the specified time ranges. Multiple `excluded_time` ranges are supported.

```json
"excluded_time": [
  {
    "start": "hh:mm",
    "end": "hh:mm"
  },
  {
    "start": "hh:mm",
    "end": "hh:mm"
  }
]
```

### 5. `allowed_weekdays`

People can enter only on the specified days of the week.

`1 = Sunday, 2 = Monday, ..., 7 = Saturday`

```json
"allowed_weekdays": [1, 2, 3, 4, 5]
```

### 6. `max_entries_per_day`

People can enter a maximum number of times per day.

```json
"max_entries_per_day": 1
```

### 7. `max_entries`

People can enter a maximum number of times in total.

```json
"max_entries": 10
```

### 8. `max_duration_per_session`

People can stay inside for a maximum number of minutes during each session.

```json
"max_duration_per_session": 120
```

### 9. `max_duration`

The total amount of time a person can stay inside, measured in minutes.

```json
"max_duration": 480
```
