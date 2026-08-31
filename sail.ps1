param(
    [Parameter(ValueFromRemainingArguments)]
    [string[]]$Arguments
)

wsl bash -c "cd /mnt/d/Code/Project/LaravelProject/iogate && ./vendor/bin/sail $($Arguments -join ' ')"
