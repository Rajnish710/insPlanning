// Insert data in database
var save = function() {
    // var z = 1;
var methods = {};
methods.createCell = function(cell, value, x, y, instance, options) {
    // let j = parseInt(y) + 1;
    var input = document.createElement('i');
    input.className = 'material-icons';
    input.style.cursor = 'pointer';
    input.innerHTML = '<a href="#" class="saveRow" accesskey="S">Save</a>';
    input.onclick = function() {
        // z++;
        var rNo = table.getSelectedRows(true)[0];
        var row = table.getRowData(rNo);
        // var employeeId = row.id;
        console.log(row);
        saveRowData(row);
        // fetchData(employeeId);
}
    cell.appendChild(input);
}
return methods;
}();
