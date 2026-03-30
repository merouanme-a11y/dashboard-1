param(
    [string]$BaseUrl = "http://localhost/Dashboard/public",
    [string]$Email = "",
    [string]$Password = "",
    [int]$Iterations = 5
)

$ErrorActionPreference = "Stop"

function Measure-Url {
    param(
        [string]$Url,
        $Session = $null,
        [int]$Count = 5
    )

    $times = @()

    for ($i = 0; $i -lt $Count; $i++) {
        $watch = [System.Diagnostics.Stopwatch]::StartNew()

        if ($null -eq $Session) {
            Invoke-WebRequest -Uri $Url -UseBasicParsing | Out-Null
        } else {
            Invoke-WebRequest -Uri $Url -WebSession $Session -UseBasicParsing | Out-Null
        }

        $watch.Stop()
        $times += [math]::Round($watch.Elapsed.TotalMilliseconds, 2)
    }

    return [pscustomobject]@{
        Url = $Url
        AverageMs = [math]::Round((($times | Measure-Object -Average).Average), 2)
        MinMs = [math]::Round((($times | Measure-Object -Minimum).Minimum), 2)
        MaxMs = [math]::Round((($times | Measure-Object -Maximum).Maximum), 2)
        TimesMs = ($times -join ", ")
    }
}

$loginUrl = "$BaseUrl/login"
$publicUrls = @(
    $loginUrl,
    "$BaseUrl/assets/bootstrap-icons/bootstrap-icons.css"
)

$authenticatedUrls = @(
    "$BaseUrl/",
    "$BaseUrl/annuaire",
    "$BaseUrl/profile",
    "$BaseUrl/admin/menus"
)

$results = @()

foreach ($url in $publicUrls) {
    Invoke-WebRequest -Uri $url -UseBasicParsing | Out-Null
    $results += Measure-Url -Url $url -Count $Iterations
}

if ($Email -ne "" -and $Password -ne "") {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Invoke-WebRequest -Uri $loginUrl -WebSession $session -UseBasicParsing
    $csrf = [regex]::Match($loginPage.Content, 'name="_csrf_token"\s+value="([^"]+)"').Groups[1].Value

    if ([string]::IsNullOrWhiteSpace($csrf)) {
        throw "CSRF token introuvable sur la page de connexion."
    }

    Invoke-WebRequest -Uri $loginUrl -Method Post -Body @{
        email = $Email
        password = $Password
        _csrf_token = $csrf
    } -WebSession $session -UseBasicParsing | Out-Null

    foreach ($url in $authenticatedUrls) {
        Invoke-WebRequest -Uri $url -WebSession $session -UseBasicParsing | Out-Null
        $results += Measure-Url -Url $url -Session $session -Count $Iterations
    }
}

$results | Format-Table -AutoSize
