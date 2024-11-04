<template>

  <!--  HEADER INFO-->
  <div v-if="this.data !== null" class="mt-2 row justify-content-md-center">
    <div class="form-group col-sm-2" v-if="this.mode === 0">
      <label class="control-label">Semestre</label>
      <input v-model="this.data.semestre" class="form-control" style="text-align: center" name="session" type="text"
             disabled>
    </div>
    <div class="form-group col-sm-2" v-if="this.mode === 0">
      <label class="control-label">Session</label>
      <input v-model="this.data.session" class="form-control" style="text-align: center" type="text" disabled>
    </div>
    <div class="form-group col-sm-2">
      <label class="control-label">Libellé</label>
      <input class="form-control" style="text-align: center" type="text" disabled
             :value="data.libelle === '' ? data.libelle_form : data.libelle">
    </div>
    <div class="form-group col-sm-2">
      <label class="control-label">Code</label>
      <input class="form-control" style="text-align: center" type="text" disabled
             :value="data.code === '' ? data.code_obj : data.code ">
    </div>
    <div class="form-group col-sm-2">
      <label class="control-label">Année</label>
      <input v-model="this.data.year" class="form-control" style="text-align: center" type="text" disabled>
    </div>
  </div>


  <!--  BUTTONS-->
  <div id="transfert_div" class="row justify-content-md-center mt-2">
    <button type="button" class="btn btn-primary" id="btn_go" :disabled="selectedRows.length === 0" @click="OnGO">
      <span v-if="selectedRows.length === 0">Continuer</span>
      <span v-else>Continuer ({{ this.selectedRows.length }} / {{ this.students.length }})</span>
    </button>
  </div>

  <!--  TABLEAU-->
  <ag-grid-vue
      class="ag-theme-alpine"
      style="height: 75vh"
      :columnDefs="columnDefs"
      :rowData="this.students"
      :defaultColDef="defaultColDef"
      pagination="true"
      suppressRowClickSelection="true"
      rowSelection="multiple"
      animateRows="true"
      :ensureDomOrder="true"
      :onSelectionChanged=onSelectionChanged
      :enableCellTextSelection="true">
  </ag-grid-vue>

</template>

<script>
import WebService from "../../WebService";
import {AgGridVue} from "ag-grid-vue3";
import "ag-grid-community/styles/ag-grid.css"; // Core CSS
import "ag-grid-community/styles/ag-theme-alpine.css"; // Theme

export default {
  name: "Selection",
  props: {
    mode: Number
  },
  components: {AgGridVue},
  emits: ['selected'],
  data() {
    return {
      data: null,
      students: null,
      selectedRows: [],
      defaultColDef: {
        sortable: true,
        filter: true,
        resizable: true,
        minWidth: 100,
        editable: false,
        flex: 1,
      },
      columnDefs: [
        {
          field: "numero",
          headerName: "Numero",
          headerCheckboxSelection: true,
          checkboxSelection: true,
          headerCheckboxSelectionFilteredOnly: true, // Selectionner que les lignes filtrées
          showDisabledCheckboxes: true,
        },
        {field: "name", headerName: "Nom"},
        {field: "surname", headerName: "Prenom"},
        {field: "birthday", headerName: "Date de naissance", comparator: this.dateComparator},
        {field: "mail", headerName: "Mail"},
        {
          headerName: "Relevé", flex: 2, cellRenderer: params => {
            if (this.mode === 0)
              return `<a target="_blank" href="${WebService.getPreviewTmpRn(params.data.numero)}">${params.data.file}</a>`;
            else
              return `<a target="_blank" href="${WebService.getPreviewTmpAttest(params.data.numero)}">${params.data.file}</a>`;
          }
        },
        {
          headerName: "Relevé déjà existant", cellStyle: {textAlign: 'center'}, cellRenderer: params => {
            if (params.data.index !== -1) {
              if (this.mode === 0)
                return `<a target="_blank" href="${WebService.getPreviewRn(params.data.numero, params.data.index)}">Voir</a>`;
              else
                return `<a target="_blank" href="${WebService.getPreviewAttest(params.data.numero, params.data.index)}">Voir</a>`;
            }
          }
        },
      ]
    }
  },
  methods: {
    dateComparator(date1, date2) { // compare deux dates au format d/m/Y
      const [day1, month1, year1] = date1.split('/').map(Number);
      const [day2, month2, year2] = date2.split('/').map(Number);
      const d1 = new Date(year1, month1 - 1, day1);
      const d2 = new Date(year2, month2 - 1, day2);
      return d1 - d2;
    },
    fetchSelection() {
      WebService.getSelection(this.mode).then(response => {
        // console.log(response.data);
        this.data = response.data.data;
        this.students = response.data.students;
      }).catch(err => {
        console.log("fail");
      });
    },
    onSelectionChanged(event) {
      this.selectedRows = event.api.getSelectedRows();
    },
    OnGO() {
      if (confirm("En continuant, les documents sélectionnés seront transférés.")) {
        this.$emit('selected', this.selectedRows.map(r => r.numero), this.students, this.data);
      }
    },
  },
  mounted() {
    this.fetchSelection();
  }
}
</script>


<style scoped>

</style>