import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';
const base = 'C:/Users/Admin/Downloads/EZiCart/storage/framework/testing/';
const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(base + 'analytics-preview.xlsx'));
console.log(await workbook.inspect({kind: 'region', sheetId: 'Summary', range: 'A7:E11', maxChars: 1600}));
for (const name of ['Summary', 'Products']) {
    const preview = await workbook.render({sheetName: name, autoCrop: 'all', scale: 1.2, format: 'png'});
    await fs.writeFile(base + 'analytics-workbook-' + name + '.png', new Uint8Array(await preview.arrayBuffer()));
}
