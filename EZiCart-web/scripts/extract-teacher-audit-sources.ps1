param([string]$SourceDirectory = 'C:\Users\Admin\Downloads')

# Read-only OOXML extraction. Never opens or rewrites the teacher's files.
Add-Type -AssemblyName System.IO.Compression.FileSystem
$outputDirectory = Join-Path $PSScriptRoot '..\docs\audits\2026-11-13'
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
function Read-ZipXml($archive, [string]$entryName) {
    $entry = $archive.GetEntry($entryName)
    if (-not $entry) { throw "Missing OOXML entry $entryName" }
    $reader = [System.IO.StreamReader]::new($entry.Open())
    try { [xml]$xml = $reader.ReadToEnd(); return ,$xml } finally { $reader.Dispose() }
}
function Namespace-Manager($xml, [string]$prefix, [string]$uri) {
    $manager = [System.Xml.XmlNamespaceManager]::new($xml.NameTable)
    $manager.AddNamespace($prefix, $uri)
    return ,$manager
}
$sources = @()
$names = @('Blackbox_Test_Cases_Seller_Modules-1.xlsx', 'Blackbox_Test_Cases_Rider_Modules-1 (1).xlsx', 'Blackbox_Test_Cases_Buyer_Modules-1.xlsx', 'Blackbox_Test_Cases_Logistics_Modules-1 (1).xlsx')
foreach ($name in $names) {
    $path = Join-Path $SourceDirectory $name
    $archive = [System.IO.Compression.ZipFile]::OpenRead($path)
    try {
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
        $strings = @()
        if ($archive.GetEntry('xl/sharedStrings.xml')) {
            $shared = Read-ZipXml $archive 'xl/sharedStrings.xml'
            $manager = Namespace-Manager $shared 's' $namespace
            $strings = @($shared.SelectNodes('//s:si', $manager) | ForEach-Object { ($_.SelectNodes('.//s:t', $manager) | ForEach-Object InnerText) -join '' })
        }
        $workbook = Read-ZipXml $archive 'xl/workbook.xml'
        $manager = Namespace-Manager $workbook 's' $namespace
        $relations = Read-ZipXml $archive 'xl/_rels/workbook.xml.rels'
        $sheets = @()
        foreach ($sheet in $workbook.SelectNodes('//s:sheet', $manager)) {
            if ($sheet.name -in @('Test Data', 'Test Files', 'Summary')) {
                $sheets += [ordered]@{name=$sheet.name; rows=@(); omitted='Fixture accounts/files and source summary remain in the original attachment; not copied into the repository.'}
                continue
            }
            $relationId = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
            $target = ($relations.Relationships.Relationship | Where-Object Id -eq $relationId).Target
            $entryName = if ($target.StartsWith('/')) { $target.TrimStart('/') } else { 'xl/' + $target }
            $sheetXml = Read-ZipXml $archive $entryName
            $sheetManager = Namespace-Manager $sheetXml 's' $namespace
            $rows = @()
            foreach ($row in $sheetXml.SelectNodes('//s:sheetData/s:row', $sheetManager)) {
                $cells = [ordered]@{}
                foreach ($cell in $row.SelectNodes('s:c', $sheetManager)) {
                    $value = $cell.SelectSingleNode('s:v', $sheetManager)
                    $text = if ($cell.t -eq 's') { $strings[[int]$value.InnerText] } elseif ($cell.t -eq 'inlineStr') { ($cell.SelectNodes('.//s:t', $sheetManager) | ForEach-Object InnerText) -join '' } elseif ($value) { $value.InnerText } else { '' }
                    if ($text -ne '') { $cells[$cell.r] = $text }
                }
                if ($cells.Count) { $rows += [ordered]@{row=[int]$row.r; cells=$cells} }
            }
            $sheets += [ordered]@{name=$sheet.name; rows=$rows; merges=@($sheetXml.SelectNodes('//s:mergeCell', $sheetManager) | ForEach-Object { $_.ref })}
        }
        $sources += [ordered]@{file=$name; sha256=(Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash; sheets=$sheets}
    } finally { $archive.Dispose() }
}
$docName = 'Ecommerce_Role_Functionality_Checklist.docx'
$docPath = Join-Path $SourceDirectory $docName
$archive = [System.IO.Compression.ZipFile]::OpenRead($docPath)
try {
    $document = Read-ZipXml $archive 'word/document.xml'
    $manager = Namespace-Manager $document 'w' 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
    $paragraphs = @()
    $index = 0
    foreach ($paragraph in $document.SelectNodes('//w:body//w:p', $manager)) {
        $index++
        $text = ($paragraph.SelectNodes('.//w:t', $manager) | ForEach-Object InnerText) -join ''
        $symbols = @($paragraph.SelectNodes('.//w:sym', $manager) | ForEach-Object { $_.GetAttribute('char', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') })
        if ($text.Trim() -ne '' -or $symbols.Count) { $paragraphs += [ordered]@{paragraph=$index; text=$text; symbols=$symbols} }
    }
    $checklist = [ordered]@{file=$docName; sha256=(Get-FileHash -LiteralPath $docPath -Algorithm SHA256).Hash; paragraphs=$paragraphs}
} finally { $archive.Dispose() }
$result = [ordered]@{extracted_for='Laravel web audit 2026-11-13'; checklist=$checklist; workbooks=$sources}
$json = $result | ConvertTo-Json -Depth 15
[System.IO.File]::WriteAllText((Join-Path $outputDirectory 'teacher-sources.json'), $json, [System.Text.UTF8Encoding]::new($false))
$checklist.paragraphs | ForEach-Object { "P$($_.paragraph): $($_.text)" }
foreach ($source in $sources) { foreach ($sheet in $source.sheets) { "$($source.file) | $($sheet.name) | $($sheet.rows.Count) nonempty rows" } }
